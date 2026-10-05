#!/usr/bin/env python3
"""
Check the German language variants without gender characters (de-standard.xml and de-DE-standard.xml).

The variant files only contain the texts that differ from their base file (de.xml or de-DE.xml), all other
texts are read from the base file. This script lists every text that a user of a variant would see with
gender characters (Benutzer:in), participle forms (Teilnehmende) or pair forms (Benutzerinnen und Benutzer),
because the variant has no own version of this text. It also lists entries of the variant that are no longer
needed, because the text id doesn't exist in the base file anymore or the text is identical to the base file.

Every finding is printed as one line "<variant file>: <text id>: <message>". The exit code is always 0,
the findings are only meant as a hint for the next update of the variant files.
"""
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

LANGUAGE_FOLDER = Path(__file__).resolve().parents[2] / 'languages'
VARIANTS = {'de-standard.xml': 'de.xml', 'de-DE-standard.xml': 'de-DE.xml'}

GENDERED_FORMS = re.compile(
    r'\w[:*_]in(nen)?\b'                      # Benutzer:in, Benutzer*innen, Benutzer_innen
    r'|\w:(r|e|n)\b'                          # ein:e, neue:r, eine:n
    r'|/-?in(nen)?\b|/-?r\b'                  # Partner/-in, Vorgesetzte/-r
    r'|\b\w+innen (und|oder) \w+'             # Benutzerinnen und Benutzer
    r'|\b\w+in (und|oder) (ein|eine|einem|einen|der|dem|den|des) \w+'  # Benutzerin oder der Benutzer
    r'|\beiner \w+in (oder|/) '               # einer Administratorin oder
    r'|\b(Teilnehmend|Empfangend|Mitwirkend|Studierend|Lehrend|Mitarbeitend)\w*'  # participle forms
)


def load_texts(path: Path) -> dict:
    return {element.get('name'): (element.text or '') for element in ET.parse(path).getroot()
            if element.tag == 'string'}


def main() -> int:
    findings = []
    for variant_file, base_file in VARIANTS.items():
        variant = load_texts(LANGUAGE_FOLDER / variant_file)
        base = load_texts(LANGUAGE_FOLDER / base_file)

        for text_id, base_text in base.items():
            text = variant.get(text_id, base_text)
            match = GENDERED_FORMS.search(text)
            if match:
                findings.append(f'{variant_file}: {text_id}: gendered form "{match.group(0)}" '
                                f'(add a version of this text to {variant_file})')

        for text_id, text in variant.items():
            if text_id not in base:
                findings.append(f'{variant_file}: {text_id}: text id doesn\'t exist in {base_file} anymore')
            elif text == base[text_id]:
                findings.append(f'{variant_file}: {text_id}: identical to {base_file}, entry can be removed')

    for finding in findings:
        print(finding)
    return 0


if __name__ == '__main__':
    sys.exit(main())
