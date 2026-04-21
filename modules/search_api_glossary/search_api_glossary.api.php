<?php

/**
 * @file
 * Hooks provided by the Search API Glossary module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * First Letter Alter.
 *
 * Alter hook to allow preprocessing prior to determining the first letter.
 *
 * @param string $source_value
 *   First Letter.
 */
function hook_search_api_glossary_source_alter(&$source_value) {
  $replacements = [
    // Map A variations.
    'À' => 'A',
    'Á' => 'A',
    'Â' => 'A',
    'Ã' => 'A',
    'Ä' => 'A',
    'Å' => 'A',
    'Ă' => 'A',
    'Ā' => 'A',
    'Ą' => 'A',
    'Æ' => 'A',
    'Ǽ' => 'A',
    'à' => 'a',
    'á' => 'a',
    'â' => 'a',
    'ã' => 'a',
    'ä' => 'a',
    'å' => 'a',
    'ă' => 'a',
    'ā' => 'a',
    'ą' => 'a',
    'æ' => 'a',
    'ǽ' => 'a',

    // Map B variations.
    'Þ' => 'B',
    'þ' => 'b',

    // Map C variations.
    'Ç' => 'C',
    'Č' => 'C',
    'Ć' => 'C',
    'Ĉ' => 'C',
    'Ċ' => 'C',
    'ç' => 'c',
    'č' => 'c',
    'ć' => 'c',
    'ĉ' => 'c',
    'ċ' => 'c',

    // Map D variations.
    'Ď' => 'D',
    'ď' => 'd',

    // Map E variations.
    'È' => 'E',
    'É' => 'E',
    'Ê' => 'E',
    'Ë' => 'E',
    'Ĕ' => 'E',
    'Ē' => 'E',
    'Ę' => 'E',
    'Ė' => 'E',
    'è' => 'e',
    'é' => 'e',
    'ê' => 'e',
    'ë' => 'e',
    'ĕ' => 'e',
    'ē' => 'e',
    'ę' => 'e',
    'ė' => 'e',

    // Map G variations.
    'Ĝ' => 'G',
    'Ğ' => 'G',
    'Ġ' => 'G',
    'Ģ' => 'G',
    'ĝ' => 'g',
    'ğ' => 'g',
    'ġ' => 'g',
    'ģ' => 'g',

    // Map H variations.
    'Ĥ' => 'H',
    'Ħ' => 'H',
    'ĥ' => 'h',
    'ħ' => 'h',

    // Map I variations.
    'Ì' => 'I',
    'Í' => 'I',
    'Î' => 'I',
    'Ï' => 'I',
    'İ' => 'I',
    'Ĩ' => 'I',
    'Ī' => 'I',
    'Ĭ' => 'I',
    'Į' => 'I',
    'ì' => 'i',
    'í' => 'i',
    'î' => 'i',
    'ï' => 'i',
    'į' => 'i',
    'ĩ' => 'i',
    'ī' => 'i',
    'ĭ' => 'i',
    'ı' => 'i',

    // Map J variations.
    'Ĵ' => 'J',
    'ĵ' => 'j',

    // Map K variations.
    'Ķ' => 'K',
    'ķ' => 'k',
    'ĸ' => 'k',

    // Map L variations.
    'Ĺ' => 'L',
    'Ļ' => 'L',
    'Ľ' => 'L',
    'Ŀ' => 'L',
    'Ł' => 'L',
    'ĺ' => 'l',
    'ļ' => 'l',
    'ľ' => 'l',
    'ŀ' => 'l',
    'ł' => 'l',

    // Map N variations.
    'Ñ' => 'N',
    'Ń' => 'N',
    'Ň' => 'N',
    'Ņ' => 'N',
    'Ŋ' => 'N',
    'ñ' => 'n',
    'ń' => 'n',
    'ň' => 'n',
    'ņ' => 'n',
    'ŋ' => 'n',
    'ŉ' => 'n',

    // Map O variations.
    'Ò' => 'O',
    'Ó' => 'O',
    'Ô' => 'O',
    'Õ' => 'O',
    'Ö' => 'O',
    'Ø' => 'O',
    'Ō' => 'O',
    'Ŏ' => 'O',
    'Ő' => 'O',
    'Œ' => 'O',
    'ò' => 'o',
    'ó' => 'o',
    'ô' => 'o',
    'õ' => 'o',
    'ö' => 'o',
    'ø' => 'o',
    'ō' => 'o',
    'ŏ' => 'o',
    'ő' => 'o',
    'œ' => 'o',
    'ð' => 'o',

    // Map R variations.
    'Ŕ' => 'R',
    'Ř' => 'R',
    'ŕ' => 'r',
    'ř' => 'r',
    'ŗ' => 'r',

    // Map S variations.
    'Š' => 'S',
    'Ŝ' => 'S',
    'Ś' => 'S',
    'Ş' => 'S',
    'š' => 's',
    'ŝ' => 's',
    'ś' => 's',
    'ş' => 's',

    // Map T variations.
    'Ŧ' => 'T',
    'Ţ' => 'T',
    'Ť' => 'T',
    'ŧ' => 't',
    'ţ' => 't',
    'ť' => 't',

    // Map U variations.
    'Ù' => 'U',
    'Ú' => 'U',
    'Û' => 'U',
    'Ü' => 'U',
    'Ũ' => 'U',
    'Ū' => 'U',
    'Ŭ' => 'U',
    'Ů' => 'U',
    'Ű' => 'U',
    'Ų' => 'U',
    'ù' => 'u',
    'ú' => 'u',
    'û' => 'u',
    'ü' => 'u',
    'ũ' => 'u',
    'ū' => 'u',
    'ŭ' => 'u',
    'ů' => 'u',
    'ű' => 'u',
    'ų' => 'u',

    // Map W variations.
    'Ŵ' => 'W',
    'Ẁ' => 'W',
    'Ẃ' => 'W',
    'Ẅ' => 'W',
    'ŵ' => 'w',
    'ẁ' => 'w',
    'ẃ' => 'w',
    'ẅ' => 'w',

    // Map Y variations.
    'Ý' => 'Y',
    'Ÿ' => 'Y',
    'Ŷ' => 'Y',
    'ý' => 'y',
    'ÿ' => 'y',
    'ŷ' => 'y',

    // Map Z variations.
    'Ž' => 'Z',
    'Ź' => 'Z',
    'Ż' => 'Z',
    'ž' => 'z',
    'ź' => 'z',
    'ż' => 'z',
  ];

  $source_value = (strtr($source_value, $replacements));
}

/**
 * @} End of "addtogroup hooks".
 */
