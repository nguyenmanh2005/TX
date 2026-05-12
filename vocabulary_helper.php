<?php
/**
 * Vocabulary Helper - Masking gambling terms with slang
 */

class VocabularyHelper {
    private static $map = [
        'thắng tiền' => ['húp GTLM'],
        'thua tiền' => ['bay màu', 'bốc hơi', 'về cõi'],
        'hết tiền' => ['nick trắng tay', 'nick khô hạn'],
        'cá cược' => ['giao lưu', 'thử vận'],
        'đặt cược' => ['thả thính', 'ra chiêu'],
        'ván bài' => ['ván giao lưu'],
        'tiền' => ['GTLM'],
        'túi' => ['nick'],
        'cược' => ['chiến', 'liều'],
        'casino' => ['trận địa'],
        'thua' => ['bay màu', 'về cõi', 'thành tro'],
        'thắng' => ['húp', 'ăn ngập mặt']
    ];

    /**
     * Masks gambling terms in a string with slang replacements.
     * Picks a random replacement if multiple options are available.
     * 
     * @param string $msg The message to mask
     * @return string The masked message
     */
    public static function mask($msg) {
        if (empty($msg)) return $msg;

        $tempMap = self::$map;
        
        // Ensure phrases are replaced before individual words
        // (already sorted by key length in the array definition above, but let's be explicit)
        uksort($tempMap, function($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        foreach ($tempMap as $search => $replaces) {
            // Case-insensitive match, handles UTF-8
            $pattern = '/' . preg_quote($search, '/') . '/iu';
            $msg = preg_replace_callback($pattern, function($matches) use ($replaces) {
                $replacement = $replaces[array_rand($replaces)];
                
                // Try to preserve capitalization of the first letter if the original match was capitalized
                if (preg_match('/^\p{Lu}/u', $matches[0])) {
                    return mb_convert_case($replacement, MB_CASE_TITLE, "UTF-8");
                }
                
                return $replacement;
            }, $msg);
        }

        return $msg;
    }
}
