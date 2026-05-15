<?php
/**
 * 📚 Vocabulary Helper - Hệ thống thay thế từ vựng thông minh
 * Chuyển đổi các từ nhạy cảm sang ngôn ngữ giải trí/slang.
 */

class VocabularyHelper {
    private static $map = [
        'thắng Gtlm' => ['húp GTLM'],
        'thua Gtlm'  => ['bay màu', 'bốc hơi', 'về cõi'],
        'thắng'      => ['húp', 'ăn ngập mặt'],
        'thua'       => ['bay màu', 'về cõi', 'thành tro'],
        'hết Gtlm'   => ['nick trắng tay', 'nick khô hạn'],
        'cá cược'    => ['giao lưu', 'thử vận'],
        'đặt cược'   => ['thả thính', 'ra chiêu'],
        'ván bài'    => ['ván giao lưu'],
        ' Gtlm'       => ['GTLM'],
        'túi'        => ['nick'],
        'cược'       => ['chiến', 'liều'],
        'casino'     => ['trận địa'],
        'tài xỉu'    => ['Xanh Đỏ Đối Kháng'],
        'xóc đĩa'    => ['Trận Địa Trắng Đỏ'],
        'bầu cua'    => ['Thế Giới Linh Thú'],
        'đá gà'      => ['Đại Chiến Thần Kê'],
        'rồng hổ'     => ['Chiến Trường Rồng Hổ']
    ];

    /**
     * Hàm callback xử lý thay thế chuỗi
     * @param string $buffer Nội dung HTML đầu ra
     * @return string Nội dung đã được thay thế
     */
    public static function mask($buffer) {
        if (empty($buffer)) return $buffer;

        // Chỉ xử lý nếu là HTML (tránh làm hỏng JSON API hoặc ảnh)
        if (strpos($buffer, '<html') === false && strpos($buffer, '<div') === false) {
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
