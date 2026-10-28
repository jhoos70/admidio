<?php

namespace AdmidioPlugin\MediaPlayer\Service;

/**
 ***********************************************************************************************
 * Sound track of MP4 videos as audio file (m4a)
 *
 * Browsers don't play a video file reliably while the page is in the background, e.g. on a phone with the screen
 * off: Chrome on Android also decodes the picture in the audio element and stops the playback when the system takes
 * the video decoder away. So the player streams only the sound track of a video in the background. The sound data
 * is copied without decoding into a new file with only the sound track, which is created on the first request and
 * kept in the data folder of Admidio.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class SoundTrackService
{
    // the movie box with the index of the samples is read into the memory, larger boxes are not supported
    private const MAX_MOVIE_BOX_SIZE = 50 * 1024 * 1024;

    /**
     * Get the audio file with the sound track of a video. The file is created if it doesn't exist yet. Sound files
     * of former versions of the video are deleted.
     * @param string $fileUuid UUID of the video in the module documents & files.
     * @param string $videoPath Path of the video file.
     * @return string|null Path of the audio file or null if the video is no MP4 file with a sound track.
     */
    public function getSoundFile(string $fileUuid, string $videoPath): ?string
    {
        $folder = ADMIDIO_PATH . FOLDER_DATA . '/media_player_sound';
        $name = $fileUuid . '-' . filesize($videoPath) . '-' . filemtime($videoPath);
        $soundPath = $folder . '/' . $name . '.m4a';
        if (is_file($soundPath)) {
            return $soundPath;
        }
        if (!is_dir($folder) && !mkdir($folder, 0770, true) && !is_dir($folder)) {
            return null;
        }
        foreach (glob($folder . '/' . $fileUuid . '-*.m4a') ?: array() as $formerPath) {
            unlink($formerPath);
        }

        // the file is written under another name first, so a second request never gets a part of it
        $tempPath = $folder . '/' . $name . '.' . bin2hex(random_bytes(4)) . '.tmp';
        try {
            if ($this->extract($videoPath, $tempPath) && rename($tempPath, $soundPath)) {
                return $soundPath;
            }
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
        return null;
    }

    /**
     * Write a new MP4 file with the movie header and the sound track of the video: ftyp, moov with mvhd and the
     * sound track whose chunk offsets point into the new mdat, and mdat with the sound data of the video.
     * @param string $videoPath Path of the video file.
     * @param string $soundPath Path of the new audio file.
     * @return bool Returns true if the file was written.
     */
    private function extract(string $videoPath, string $soundPath): bool
    {
        $video = fopen($videoPath, 'rb');
        if ($video === false) {
            return false;
        }
        try {
            $videoSize = (int)filesize($videoPath);
            $moov = null;
            for ($offset = 0; $offset + 8 <= $videoSize; $offset = $box['end']) {
                fseek($video, $offset);
                $box = $this->readHeader((string)fread($video, 16), 0, $offset, $videoSize);
                if ($box === null) {
                    return false;
                }
                if ($box['type'] === 'moov') {
                    $moov = $box;
                    break;
                }
            }
            if ($moov === null || $moov['end'] - $moov['start'] > self::MAX_MOVIE_BOX_SIZE) {
                return false;
            }
            fseek($video, $moov['start']);
            $data = (string)fread($video, $moov['end'] - $moov['start']);
            $moov = $this->readHeader($data, 0, 0, strlen($data));

            $mvhd = $this->child($data, $moov, 'mvhd');
            $trak = null;
            foreach ($this->boxes($data, $moov['start'] + $moov['header'], $moov['end']) as $box) {
                $mdia = $box['type'] === 'trak' ? $this->child($data, $box, 'mdia') : null;
                $hdlr = $mdia !== null ? $this->child($data, $mdia, 'hdlr') : null;
                if ($hdlr !== null && substr($data, $hdlr['start'] + $hdlr['header'] + 8, 4) === 'soun') {
                    $trak = $box;
                    break;
                }
            }
            if ($mvhd === null || $trak === null) {
                return false;
            }
            $minf = $this->child($data, $this->child($data, $trak, 'mdia'), 'minf');
            $stbl = $minf !== null ? $this->child($data, $minf, 'stbl') : null;
            $stsc = $stbl !== null ? $this->child($data, $stbl, 'stsc') : null;
            $stsz = $stbl !== null ? $this->child($data, $stbl, 'stsz') : null;
            $chunkBox = $stbl !== null ? ($this->child($data, $stbl, 'stco') ?? $this->child($data, $stbl, 'co64')) : null;
            if ($stsc === null || $stsz === null || $chunkBox === null) {
                return false;
            }
            $wide = $chunkBox['type'] === 'co64';

            // size of each chunk from the samples per chunk (stsc) and the sample sizes (stsz)
            $chunkCount = $this->uint32($data, $chunkBox['start'] + $chunkBox['header'] + 4);
            $stscCount = $this->uint32($data, $stsc['start'] + $stsc['header'] + 4);
            $sampleSize = $this->uint32($data, $stsz['start'] + $stsz['header'] + 4);
            $chunks = array();
            $sample = 0;
            for ($chunk = 1, $entry = 0; $chunk <= $chunkCount; $chunk++) {
                while ($entry + 1 < $stscCount && $this->uint32($data, $stsc['start'] + $stsc['header'] + 8 + ($entry + 1) * 12) <= $chunk) {
                    $entry++;
                }
                $samples = $this->uint32($data, $stsc['start'] + $stsc['header'] + 8 + $entry * 12 + 4);
                $size = 0;
                for ($i = 0; $i < $samples; $i++, $sample++) {
                    $size += $sampleSize !== 0 ? $sampleSize : $this->uint32($data, $stsz['start'] + $stsz['header'] + 12 + $sample * 4);
                }
                $position = $chunkBox['start'] + $chunkBox['header'] + 8 + ($chunk - 1) * ($wide ? 8 : 4);
                $chunks[] = array($wide ? $this->uint64($data, $position) : $this->uint32($data, $position), $size);
            }

            // new file: ftyp, moov with the movie header and the sound track, mdat with the sound data
            $ftyp = pack('N', 24) . 'ftypM4A ' . pack('N', 0) . 'M4A isom';
            $trakData = substr($data, $trak['start'], $trak['end'] - $trak['start']);
            $moovSize = 8 + ($mvhd['end'] - $mvhd['start']) + strlen($trakData);
            $offset = strlen($ftyp) + $moovSize + 8;
            foreach ($chunks as $index => [$chunkOffset, $chunkSize]) {
                $position = $chunkBox['start'] - $trak['start'] + $chunkBox['header'] + 8 + $index * ($wide ? 8 : 4);
                $trakData = substr_replace($trakData, $wide ? pack('J', $offset) : pack('N', $offset), $position, $wide ? 8 : 4);
                $offset += $chunkSize;
            }
            $dataSize = $offset - strlen($ftyp) - $moovSize;
            if ($dataSize > 0xFFFFFFFF || (!$wide && $offset > 0xFFFFFFFF)) {
                return false;
            }

            $sound = fopen($soundPath, 'wb');
            if ($sound === false) {
                return false;
            }
            fwrite($sound, $ftyp . pack('N', $moovSize) . 'moov' . substr($data, $mvhd['start'], $mvhd['end'] - $mvhd['start'])
                . $trakData . pack('N', $dataSize) . 'mdat');
            foreach ($chunks as [$chunkOffset, $chunkSize]) {
                if (stream_copy_to_stream($video, $sound, $chunkSize, $chunkOffset) !== $chunkSize) {
                    fclose($sound);
                    return false;
                }
            }
            return fclose($sound);
        } finally {
            fclose($video);
        }
    }

    /**
     * Read the header of a box.
     * @param string $data Bytes that start with the header.
     * @param int $position Position of the header in $data.
     * @param int $start Position of the box in the file or in the parent box.
     * @param int $end End of the file or of the parent box.
     * @return array{type: string, start: int, header: int, end: int}|null
     */
    private function readHeader(string $data, int $position, int $start, int $end): ?array
    {
        if (strlen($data) < $position + 8) {
            return null;
        }
        $size = $this->uint32($data, $position);
        $header = 8;
        if ($size === 1) {
            if (strlen($data) < $position + 16) {
                return null;
            }
            $size = $this->uint64($data, $position + 8);
            $header = 16;
        } elseif ($size === 0) {
            $size = $end - $start;
        }
        if ($size < $header || $start + $size > $end) {
            return null;
        }
        return array('type' => substr($data, $position + 4, 4), 'start' => $start, 'header' => $header, 'end' => $start + $size);
    }

    /**
     * @return array<int,array{type: string, start: int, header: int, end: int}> The boxes between start and end.
     */
    private function boxes(string $data, int $start, int $end): array
    {
        $boxes = array();
        while ($start + 8 <= $end && ($box = $this->readHeader($data, $start, $start, $end)) !== null) {
            $boxes[] = $box;
            $start = $box['end'];
        }
        return $boxes;
    }

    /**
     * @return array{type: string, start: int, header: int, end: int}|null The first child box with the type.
     */
    private function child(string $data, ?array $box, string $type): ?array
    {
        if ($box === null) {
            return null;
        }
        foreach ($this->boxes($data, $box['start'] + $box['header'], $box['end']) as $child) {
            if ($child['type'] === $type) {
                return $child;
            }
        }
        return null;
    }

    private function uint32(string $data, int $position): int
    {
        return unpack('N', $data, $position)[1];
    }

    private function uint64(string $data, int $position): int
    {
        return unpack('J', $data, $position)[1];
    }
}
