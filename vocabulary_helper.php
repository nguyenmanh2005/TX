<?php
/**
 * 📚 Vocabulary Helper - Hệ thống thay thế từ vựng thông minh
 * Chuyển đổi các từ nhạy cảm sang ngôn ngữ giải trí/slang.
 */

class VocabularyHelper {
    private static $map = [
        'thắng GTLM'            => ['húp GTLM'],
        'thua GTLM'             => ['bay màu', 'bốc hơi', 'về cõi'],
        'hết GTLM'              => ['nick trắng tay', 'nick khô hạn'],
        'thắng Gtlm'            => ['húp GTLM'],
        'thua Gtlm'             => ['bay màu', 'bốc hơi', 'về cõi'],
        'hết Gtlm'              => ['nick trắng tay', 'nick khô hạn'],
        'sòng bài'              => ['trận địa'],
        'máy đánh bạc'          => ['máy vận may'],
        'slot machine'          => ['máy vận may'],
        'giao lưu giải trí'     => ['giao lưu giải trí'],
        'cờ bạc'                => ['giao lưu giải trí'],
        'đánh bạc'              => ['giao lưu giải trí'],
        'cá cược'               => ['giao lưu', 'thử vận'],
        'đặt cược'              => ['thả thính', 'ra chiêu'],
        'ván bài'               => ['ván giao lưu'],
        'thắng'                 => ['húp', 'ăn ngập mặt'],
        'thua'                  => ['bay màu', 'về cõi', 'thành tro'],
        'túi'                   => ['nick'],
        'cược'                  => ['chiến', 'Chiến'],
        'casino'                => ['trận địa'],
        'xóc đĩa'               => ['Trận Địa Trắng Đỏ'],
        'Chiến Trường Linh Thú' => ['Thế Giới Linh Thú'],
        'đá gà'                 => ['Đại Chiến Thần Kê'],
        'rồng hổ'                => ['Chiến Trường Rồng Hổ']
    ];

    /**
     * Hàm callback xử lý thay thế chuỗi (hỗ trợ cả HTML, JSON và Text thuần từ Bot/Chat)
     * @param string $buffer Nội dung đầu ra cần lọc từ vựng
     * @return string Nội dung đã được thay thế
     */
    public static function mask($buffer) {
        if (empty($buffer) || !is_string($buffer)) return $buffer;

        // Tránh làm hỏng dữ liệu nhị phân (ảnh, zip, pdf, v.v.)
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', substr($buffer, 0, 500))) {
            return $buffer;
        }

        $tempMap = self::$map;
        
        // Sắp xếp theo độ dài từ khóa giảm dần để ưu tiên thay thế cụm từ trước
        uksort($tempMap, function($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        foreach ($tempMap as $search => $replaces) {
            // Sử dụng regex để tìm kiếm (không phân biệt hoa thường, hỗ trợ Unicode)
            $pattern = '/' . preg_quote($search, '/') . '/iu';
            
            $buffer = preg_replace_callback($pattern, function($matches) use ($replaces) {
                $replacement = $replaces[array_rand($replaces)];
                
                // Giữ nguyên kiểu chữ (Hoa/Thường) của chữ cái đầu tiên
                if (preg_match('/^\p{Lu}/u', $matches[0])) {
                    return mb_convert_case($replacement, MB_CASE_TITLE, "UTF-8");
                }
                
                return $replacement;
            }, $buffer);
        }

        return $buffer;
    }
}
