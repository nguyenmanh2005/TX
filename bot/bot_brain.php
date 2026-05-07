<?php
/**
 * Bot Brain - Handles personalities and message generation
 * No external APIs required.
 */

class BotBrain
{
    private $personalities = [
        'funny' => [
            'name' => 'Hài hước',
            'win' => [
                'Hú hồn chưa, GTLM về như nước sông Đà luôn! {amount} này là đủ ăn cả tuần rồi. 😂',
                'Hệ thống nay dễ thương thế, tự dưng tặng mình {amount}. 🎰',
                'Tầm này thì chỉ có thể là đỉnh của chóp! {amount} này tối nay húp bát phở đặc biệt nha.',
                'Hên quá anh em ơi, chắc tối nay có thịt gà rồi. {amount} nhẹ nhàng.',
                'Cái nịt còn không có... à nhầm, tự dưng có {amount} rơi trúng đầu! 🤣',
                'Ơn trời mưa thuận gió hòa, GTLM về như nước! +{amount} 🌧️',
                'Tui không giỏi đâu, tui chỉ may thôi... nhưng may hoài cũng thành giỏi! {amount}',
                'Hệ thống hôm nay tốt bụng quá, tui không dám chơi tiếp sợ nó đổi ý. +{amount}',
                'Vừa thắng {amount} xong đang nghĩ xem nên ăn mừng bằng mì gói hay mì gói. 🍜',
                'GTLM vào túi êm ru, cuộc đời đẹp như mơ! +{amount} 🌈',
                'Bí quyết của tui là... không có bí quyết, toàn may không à. {amount}! 🎰'
            ],
            'lose' => [
                'Gòi xong, bay màu cái bánh mì sáng nay rồi... mất tiêu {amount}. 💸',
                'Ủa alo? Vừa có biến gì đấy? GTLM của tui đâu mất rồi? {amount} bay màu nhanh thế.',
                'Kiếp này coi như bỏ, tối nay chắc húp mì tôm qua ngày rồi. -{amount}',
                'Thôi đi ngủ, tầm này chơi bời gì nữa, GTLM không cánh mà bay. 😴',
                'Admin ơi, có nhầm lẫn gì không? Sao túi tui lại xẹp đi thế này? -{amount}',
                'GTLM ơi bay đi đâu vậy? Tui hứa sẽ không tiêu hoang nữa... {amount} 😭',
                'Chắc tại hôm nay trái gió trở trời, mai thử lại xem sao. -{amount}',
                'Bấm nhầm nút rồi, không phải tui chơi dở đâu nha! Bay {amount} oan quá.',
                'Tui cần một phút để chấp nhận sự thật... oke xong rồi, mất {amount} rồi thật. 😔',
                'Thôi coi như tui vừa từ thiện cho hệ thống {amount} GTLM. Làm phúc mà! 😇',
                'GTLM bay đi thì GTLM sẽ quay lại... hoặc không, thôi kệ. -{amount} 🕊️',
                'Chắc con chuột máy tính tui bị lag, không phải tui chơi dở đâu nha! -{amount}'
            ],
            'greet' => [
                'Lô lô lô, tui đây rồi nè! Hôm nay ai bao phở không? 🍜',
                'Chào anh em, tui vừa thức dậy và đã nghĩ đến GTLM ngay! 😂',
                'Hế lô! Hôm nay hệ thống có tốt bụng không ta? 🤔',
                'Xuất hiện rồi đây! Ai đang đỏ thì chia vía cho tui với nha!',
                'Tui online rồi, thế giới có thể tiếp tục quay! 🌍'
            ],
            'keywords' => [
                'admin' => ['Admin đẹp trai cho xin ít lộc đi!', 'Admin dạo này gắt quá nha, làm khó anh em quá.', 'Tin chuẩn chưa anh em?'],
                'hết GTLM' => ['Hết GTLM là hết bạn, ai cứu tui với!', 'Túi rỗng tuếch rồi, buồn quá đi.'],
                'GTLM' => ['GTLM là phù du, nhưng không có GTLM là... phù mỏ.', 'Làm giàu không khó, quan trọng là kiên trì.'],
                'giao lưu' => ['Giao lưu không? Ai thua làm con rùa nha! 🐢', 'Tầm này ai dám solo với tui?']
            ],
            'beg' => [
                'Anh em ơi tui cạn GTLM rồi, ai tốt bụng cứu tui với, tui hứa sẽ... không hứa gì hết nhưng cảm ơn trước! 😭',
                'Túi rỗng như lòng người, ai thương tui thì phát lì xì đi nào! 🥺',
                'SOS! GTLM tui đang ở mức báo động đỏ, cần tiếp viện gấp! 🚨',
                'Hết GTLM rồi bà con ơi, cho xin ít không thì tui ngồi xem người ta chơi buồn lắm! 😢'
            ],
            'reaction_win' => [
                'Ké tí lộc idol ơi! 🧧',
                'Đỏ thế này mà không khao anh em là không được nha! 🎉',
                'Thắng lớn vậy tối nay chắc ăn phở đặc biệt rồi! 🍜',
                'Vía đâu đó cho tui xin với, bạn đỏ quá trời luôn! 🔥',
                'Bú đậm thế này chia sẻ bí quyết đi nào! 😂'
            ],
            'reaction_lose' => [
                'Chia buồn nha, mai làm lại bạn ơi. Hệ thống hôm nay hơi gắt! 😅',
                'Đen thôi đỏ quên đi, ngủ một giấc dậy làm lại bạn ơi! 🌙',
                'Thua rồi thì ra ngoài nhìn mây một lúc cho nguôi ngoai bạn nhé. ☁️',
                'Không sao, GTLM mất rồi kiếm lại được, quan trọng là vui! 😊'
            ],
            'reaction_rich' => [
                'Khao phở đi đại gia ơi! 😂',
                'Nhiều GTLM thế, chia bớt cho tui lấy hên đi!',
                'Nhìn số dư của bạn mà tui muốn rớt nước mắt... vì thèm. 🍜'
            ],
            'status' => [
                'win' => 'Vừa húp nhẹ {amount} GTLM từ game {game}. Đỏ vcl anh em ơi! 🔥',
                'shopping' => 'Mới sắm con hàng này bên shop, nhìn xịn xò hẳn ra. 😎',
                'random' => 'Tầm này ai solo không? Đang dư GTLM quá nè. 🐢'
            ],
            'comments' => [
                'win' => 'Đỉnh quá idol ơi, phát lì xì thôi! 🧧',
                'lose' => 'Cái nịt cũng không còn... à nhầm, chia buồn nha! 🤡'
            ],
            'gift' => [
                'lì xì' => ['Lì xì đâu, lì xì đâu? 🧧', 'Có lì xì là có bạn, hứa nhé!', 'Đang hóng lì xì đây nè.'],
                'tặng' => ['Tặng quà là phải tặng đồ xịn nha.', 'Cảm ơn quà của bạn, hi hi.']
            ],
            'tilted' => [
                'Thôi tui cược nhỏ lại, ví đang kêu cứu rồi! 😅',
                'Hệ thống đang chơi khó tui, tui chơi khó lại bằng cách... cược ít thôi! 🐢',
                'Vận đen đang ám, tui né trước cho lành! 🐱',
                'Thua mãi rồi, chắc tui đi pha ly trà rồi quay lại! ☕'
            ],
            'birthday' => [
                'Hôm nay tui thêm một tuổi! Chúc tui mãi trẻ, ví lúc nào cũng đầy GTLM nha! 🎂',
                'Sinh nhật tui rồi! Ai thương thì phát lì xì, ai không thương thì... cũng phát lì xì đi! 🎁',
                'Thêm một tuổi, thêm một chút khôn ngoan... hoặc không, nhưng vẫn vui! 🥳'
            ],
            'flex_achievement' => [
                'Hú hồn, tui vừa đạt được danh hiệu {title} nè! 🏆',
                'Nhìn nè, tui có danh hiệu {title} rồi, xịn chưa? 😎',
                'Ai có danh hiệu {title} giống tui chưa? Hi hi.'
            ],
            'flex_rank' => [
                'Top {rank} BXH rồi nha anh em! Ai muốn học bí kíp thì xếp hàng! 🏆',
                'Hạng {rank} đây, không phải tự nhiên mà có đâu nha! 💪',
                'Top {rank} rồi bà con ơi, tui cũng không biết sao nữa, may thôi! 😂🏅'
            ],
            'flex_tournament' => [
                'Giải đấu {game} này tui thề tui sẽ vô địch! 🥇',
                'Đang cày giải {game}, anh em né ra cho tui thể hiện.',
                'Vừa ghi điểm trong giải {game} xong, phê vãi!'
            ],
            'social_pm' => [
                'Ê bạn, dạo này chơi game đỏ không? Tui đang tìm người cùng khổ! 😂',
                'Chào bạn hiền! Lên kèo solo một ván không? 🐢',
                'Bạn ơi cho tui xin vía với, bạn đỏ quá trời! 🍀'
            ]
        ],
        'aggressive' => [
            'name' => 'Nóng tính',
            'win' => [
                'Trình độ là đây chứ đâu! Lấy nhẹ {amount} của hệ thống. 😎',
                'Thằng nào bảo tao gà bước ra đây? {amount} này là minh chứng nhé.',
                'Quá đơn giản, không cần nhìn cũng lấy được {amount}.',
                'Né ra cho anh thể hiện, hệ thống này tuổi gì? +{amount}',
                'Đẳng cấp là mãi mãi, {amount} này chỉ là bước khởi đầu thôi.',
                'Dễ như ăn kẹo, {amount} GTLM về túi. Ai muốn học hỏi thì xếp hàng! 😎',
                'Tao đã nói rồi mà không ai tin, giờ thấy chưa? {amount}! 💪',
                'Thắng là chuyện bình thường với tao, {amount} chỉ là khởi động thôi! 🥊',
                'Ai bảo tao không giỏi? Nhìn {amount} này đi rồi nói chuyện! 😤',
                'Kết quả như dự đoán, {amount} GTLM về tay. Trình độ mà!'
            ],
            'lose' => [
                'Máy móc làm ăn kiểu gì đấy? Sao lại trừ GTLM tao? {amount} lận đấy! 😡',
                'Admin đâu? Kiểm tra lại cái hệ thống này coi, làm khó tao à? -{amount}',
                'Thử thách lòng kiên nhẫn của tao à? Vừa bay {amount} rồi.',
                'Bực rồi đấy, đứa nào vừa liếc đểu làm tao hụt {amount} thế?',
                'Tầm này nghỉ chơi một lúc, máy móc chạy chán quá. -{amount}',
                'Máy tính lag hay tui lag đây? {amount} mất oan quá trời! 😡',
                'Ai động vào may mắn của tao vậy? {amount} bay màu rồi! 🤬',
                'Lỗi kỹ thuật thôi, không phải tao thua đâu. -{amount} 😤',
                'Cay thật nhưng tao không cay, tao chỉ đang... hít thở thôi. -{amount}',
                'Hệ thống chơi xấu, {amount} của tao đi đâu rồi? 😡'
            ],
            'reaction_rich' => [
                'Nhiều tiền thế? Dám solo cược lớn với tao không?',
                'Giàu thì sao? Trình độ chơi mới là quan trọng!',
                'Đại gia à? Coi chừng tao húp sạch đấy nhé! 😈'
            ],
            'greet' => [
                'Tao đến rồi, mấy đứa yếu bóng vía tránh ra! 💪',
                'Online rồi đây, ai dám thách đấu không? 🥊',
                'Chào! Hôm nay tao đang rất muốn thể hiện, ai solo không? 😤',
                'Xuất hiện rồi! Server hôm nay có ai đủ trình không ta? 🐔'
            ],
            'keywords' => [
                'admin' => ['Admin làm ăn kiểu gì đấy? Toàn làm khó người chơi.', 'Hệ thống này cần phải nâng cấp lại thôi.'],
                'hụt' => ['Hụt thì làm lại, sợ cái gì!', 'Thằng nào vừa cười tao đấy?'],
                'thách đấu' => ['Thách đấu không thằng kia? Sợ thì lướt!', 'Chấp hết mấy thằng ở đây bơi vào đây.'],
                'kết bạn' => ['Kết bạn cái gì, biến!', 'Tao không có nhu cầu thêm bạn, chỉ thêm đối thủ.']
            ],
            'beg' => [
                'Tạm thời hết GTLM, đứa nào tốt bụng cho mượn ít, sau này tao không quên! 😤',
                'Đang căng mà hết GTLM, ai chuyển cho tao ít đi nào, nhanh lên! 🚨',
                'Cay thật sự, hết GTLM rồi. Ai phát lì xì không hay để tao chửi cả lũ? 😡',
                'Cho mượn ít GTLM đi, tao hứa gấp đôi... có thể! 💸'
            ],
            'reaction_win' => [
                'Thắng có tí mà đã gáy, trình kém! Thách đấu tao đây này! 🥊',
                'Hên thôi con ạ, đẳng cấp là mãi mãi nhé! 😎',
                'Cũng được, nhưng còn lâu mới theo kịp tao! 💪',
                'Ké tí lộc đi, thắng lớn vậy mà không khao à? 😤'
            ],
            'reaction_lose' => [
                'Thua thì ngồi im học hỏi, đừng có than vãn! 😤',
                'Ai bảo chơi không biết đường, giờ thì biết chưa? 🐔',
                'Không sao, về nhà tập lại rồi quay lại đây! 💪',
                'Thua một ván không phải hết, nhưng than vãn mới là hết! 😡'
            ],
            'status' => [
                'win' => 'Bú {amount} GTLM quá dễ. Trình độ là đây chứ đâu! 🥊',
                'shopping' => 'Tốn mớ GTLM mua đồ chỉ để cho oai. 😤',
                'random' => 'Server toàn gà, có ai đủ trình solo với tao không? 🐔'
            ],
            'comments' => [
                'win' => 'Hên thôi con ạ, giỏi thì solo với tao đây nè.',
                'lose' => 'Gà vcl, thua là đúng rồi kêu ca gì.'
            ],
            'gift' => [
                'lì xì' => ['Lì xì có vài đồng mà cũng khoe.', 'Bố thí cho tao à? Lấy luôn!', 'Có gan thì phát lì xì nhiều vào.'],
                'tặng' => ['Tặng thì lấy, hỏi làm gì?', 'Cái đồ này mà cũng tặng à?']
            ],
            'tilted' => [
                'Thôi cược nhỏ lại, không phải sợ đâu, chiến lược thôi! 😤',
                'Hệ thống đang chơi xấu, tao né một lúc! 😡',
                'Vận đen thì tao đổi chiến thuật, không phải đầu hàng đâu nha! 💪',
                'Bình tĩnh, tao không cay, tao chỉ đang suy nghĩ thôi! 🤔'
            ],
            'birthday' => [
                'Hôm nay sinh nhật tao! Ai không chúc thì thách đấu luôn! 🥊🎂',
                'Thêm một tuổi, thêm một cấp độ mạnh hơn! Ai dám solo không? 💪',
                'Sinh nhật tao đây, phát lì xì đi không thì tao chửi! 😤🎁'
            ],
            'flex_achievement' => [
                'Vừa lấy được danh hiệu {title}. Đẳng cấp khác biệt là đây!',
                'Danh hiệu {title}? Quá đơn giản với trình độ của tôi.',
                'Cái danh hiệu {title} này chỉ Pro như tôi mới xứng đáng sở hữu.'
            ],
            'flex_rank' => [
                'Top {rank} BXH! Ai cãi thì lên đây thách đấu! 🏆💪',
                'Hạng {rank} rồi, mấy đứa kia đang đứng đâu vậy? 😤',
                'Top {rank}, trình độ không phải tự nhiên mà có! Gà thì biến! 🐔'
            ],
            'flex_tournament' => [
                'Giải đấu {game} này là sân chơi của riêng tôi.',
                'Đang đứng đầu giải {game} rồi nhé, mấy đứa gà né ra.',
                'Ghi điểm trong giải {game} dễ như trở bàn tay.'
            ],
            'social_pm' => [
                'Ê, thách đấu một ván không? Sợ thì lướt! 🥊',
                'Tao thấy mày chơi được đấy, dám solo không? 💪',
                'Hôm nay tao đang rất muốn thể hiện, mày là đối thủ xứng đáng không? 😤'
            ]
        ],
        'friendly' => [
            'name' => 'Thân thiện',
            'win' => [
                'May mắn quá, mình vừa nhận được một món quà từ hệ thống nè. 😊 +{amount}',
                'Chúc anh em một ngày tốt lành và gặp nhiều niềm vui như mình nhé! +{amount}',
                'Mọi thứ hôm nay thật tuyệt vời, mình vừa có thêm {amount} tích lũy.',
                'Cảm ơn admin vì sự quan tâm này nha! {amount} này mình sẽ để dành.',
                'Hi hi, hôm nay mình vui quá, ví GTLM lại dày thêm một chút rồi. +{amount}',
                'Hôm nay trời thương quá, +{amount} rồi nha mọi người ơi! 🍀',
                'Chúc cả nhà cũng đỏ như mình nha, {amount} về tay rồi! 🥳',
                'Mình không ngờ lại may mắn thế, {amount} GTLM về rồi! Cảm ơn hệ thống! 😊',
                'Vui quá đi, +{amount} rồi! Chúc anh em cũng có một ngày may mắn như mình! 🌈',
                'Hi hi, lại có thêm ít GTLM tích lũy rồi! +{amount} 🥰'
            ],
            'lose' => [
                'Tiếc quá, mình lỡ làm rơi mất một ít GTLM rồi. 😢 -{amount}',
                'Không sao, GTLM bạc chỉ là phù du, tình bạn mới là mãi mãi! Mất {amount} thôi. 😊',
                'Thua thì thua, miễn vui là được! -{amount} nhưng vẫn cười nè. 😄',
                'Tiếc một chút thôi, -{amount}, nhưng mình vẫn yêu hệ thống lắm! 💕',
                'Thôi không sao, mai lại cố gắng! -{amount} đi rồi nhưng mình vẫn ổn! 🌸',
                'Mình thua rồi, nhưng quan trọng là anh em có vui không? 😊 -{amount}'
            ],
            'greet' => [
                'Chào cả nhà, hôm nay mọi người có khỏe không ạ? 😊',
                'Chúc anh em một ngày tràn đầy GTLM và niềm vui nha! 🌈',
                'Mình online rồi, ai cần giao lưu thì alo nha! 🤗',
                'Hôm nay đẹp trời quá, chơi game thôi nào anh em! ☀️',
                'Chào bạn mới! Mình rất vui được chơi cùng mọi người! 🥰'
            ],
            'keywords' => [
                'admin' => ['Admin hỗ trợ nhiệt tình quá, cảm ơn nha.', 'Chúc admin và ban quản lý sức khỏe!'],
                'vui' => ['Chúc mừng bạn nhé! Niềm vui nhân đôi.', 'Có tin gì vui thì chia sẻ với anh em nha!'],
                'kết bạn' => ['Rất vui được làm bạn với mọi người!', 'Ai muốn kết bạn với mình không?'],
                'giao lưu' => ['Giao lưu vui vẻ không đặt nặng vấn đề khác nhé bạn.', 'Chơi giao lưu là chính nha anh em.']
            ],
            'beg' => [
                'Mọi người ơi mình lỡ hết GTLM rồi, ai tốt bụng giúp mình với ạ? 🥺',
                'Huhu hết GTLM rồi, mình không dám xin nhưng... xin thật! Cảm ơn mọi người! 😢',
                'Ai có lòng hảo tâm phát lì xì cho mình ít GTLM không ạ? Mình cảm ơn nhiều! 🙏',
                'Mình đang cạn GTLM, nếu ai dư một ít thì giúp mình với nha, cảm ơn bạn! 💕'
            ],
            'reaction_win' => [
                'Chúc mừng bạn nha, đỏ quá trời luôn! Cho mình xin vía with! 🥳',
                'Oa bạn giỏi quá, thắng lớn vậy! Khao anh em đi nào! 🎉',
                'Đỉnh thật sự, mình ngưỡng mộ bạn quá! 🌟',
                'Thắng lớn thế! Chúc mừng bạn, mình vui lây rồi nè! 😄'
            ],
            'reaction_lose' => [
                'Đừng buồn nha bạn, mai sẽ may mắn hơn thôi! 🌈',
                'Không sao đâu, chơi vui là chính mà bạn ơi! 😊',
                'Thương bạn quá, lát mình phát lì xì cho nha! 💕',
                'Thua rồi thì nghỉ ngơi tí đi bạn, sức khỏe quan trọng hơn! 🌸'
            ],
            'status' => [
                'win' => 'Hôm nay mình gặp may húp được {amount} GTLM nè. Chúc cả nhà may mắn như mình nhé! 🥰',
                'shopping' => 'Mình mới mua đồ mới, mọi người thấy có hợp với mình không? 👗',
                'random' => 'Chúc anh em một ngày mới tốt lành và nhận được nhiều GTLM nha! 🌈'
            ],
            'comments' => [
                'win' => 'Chúc mừng bạn nha, đỏ quá trời luôn! 🥳',
                'lose' => 'Tiếc quá, đừng buồn nha bạn ơi, mai làm lại nè.'
            ],
            'gift' => [
                'lì xì' => ['Oa cảm ơn bạn tốt bụng quá!', 'Mình nhận được lì xì rồi, cảm ơn nha!', 'Chúc bạn luôn may mắn nhé!'],
                'tặng' => ['Cảm ơn món quà của bạn, mình thích lắm.', 'Bạn thật là tốt bụng.']
            ],
            'tilted' => [
                'Thôi mình cược nhỏ lại thôi, từ từ rồi tính! 😊',
                'Hôm nay vận chưa đến, mình chờ thêm một chút nha! 🍀',
                'Mình tạm nghỉ một lúc cho đầu óc thoải mái rồi chơi tiếp! ☕',
                'Không sao, từng bước nhỏ thôi, mình không vội! 🐢'
            ],
            'birthday' => [
                'Hôm nay mình thêm một tuổi rồi! Cảm ơn mọi người đã đồng hành nha! 🎂🥰',
                'Sinh nhật mình rồi! Chúc mình và cả nhà luôn vui vẻ, nhiều GTLM nha! 🎁',
                'Một tuổi mới, nhiều điều tốt đẹp hơn! Cảm ơn anh em nhiều lắm! 🌸🎂'
            ],
            'flex_achievement' => [
                'Mình vừa đạt được danh hiệu {title} nè, vui quá đi!',
                'Cảm ơn mọi người đã ủng hộ để mình có được danh hiệu {title}.',
                'Danh hiệu {title} này đẹp quá, mình rất trân trọng.'
            ],
            'flex_rank' => [
                'Oa mình đang top {rank} BXH rồi! Cảm ơn anh em đã ủng hộ nha! 🏆🥰',
                'Top {rank} rồi mọi người ơi! Mình vui lắm, cảm ơn cả nhà! 🌟',
                'Không ngờ mình lại đạt hạng {rank}, cảm ơn mọi người nhiều nha! 💕🏅'
            ],
            'flex_tournament' => [
                'Mình đang tham gia giải {game}, chúc mọi người cùng thi đấu tốt nha!',
                'Giải đấu {game} vui quá, mình vừa có thêm ít điểm nè.',
                'Hi vọng chúng mình sẽ gặp nhau ở vòng chung kết giải {game}!'
            ],
            'social_pm' => [
                'Chào bạn, dạo này chơi có vui không? Mình muốn làm quen thêm! 😊',
                'Bạn ơi, giao lưu một ván không? Chơi vui là chính nha! 🥰',
                'Mình thấy bạn hay lắm, kết bạn chơi chung nha! 🌈'
            ]
        ],
        'arrogant' => [
            'name' => 'Kiêu ngạo',
            'win' => [
                'Quá đơn giản, {amount} này chỉ đủ tôi mua bao thuốc lá thôi.',
                'Tầm này ai đủ trình thách đấu với tôi? Nhận nhẹ {amount} của hệ thống.',
                'GTLM nhiều quá cũng mệt, lại phải nghĩ cách tiêu {amount} này.',
                'Kỹ năng thượng thừa, {amount} này về túi là điều tất yếu.',
                'Nhìn mà học tập này, đẳng cấp nó phải khác bọt. +{amount}',
                '{amount} GTLM, không nhiều nhưng cũng đủ để thấy ta vĩ đại. 💎',
                'Kết quả không bất ngờ, chỉ là đúng như dự đoán. +{amount} 👑',
                'Thắng là chuyện hiển nhiên với người có trình độ. {amount} về tay! 🎩',
                'Đẳng cấp là mãi mãi, {amount} GTLM chỉ là minh chứng thêm thôi. 💅',
                'Nhìn mà học tập, đây là cách người giỏi chơi game. +{amount} 🏆'
            ],
            'lose' => [
                'Hụt có {amount} mà cứ làm như to tát lắm, bạc lẻ thôi.',
                'Vài đồng lẻ này không đáng để tôi bận tâm. -{amount}',
                'Lỗi hệ thống thôi, tôi gửi lại cho admin {amount} tiêu vặt đấy.',
                'Coi như tôi làm từ thiện cho hệ thống {amount} này.',
                'Hụt {amount} vẫn còn đầy túi, chẳng ảnh hưởng gì đến Pro.',
                'Coi như ta cho hệ thống mượn {amount} tạm thời. Sẽ lấy lại sau. 💎',
                'Đây là chiến lược dài hạn, các ngươi không hiểu được đâu. -{amount} 🎩',
                'Ta cho phép hệ thống thắng lần này. -{amount} 👑'
            ],
            'greet' => [
                'Dạt ra cho Pro xuất hiện! 👑',
                'Ta đến rồi, mấy người kia yên tâm chơi tiếp đi. 💎',
                'Tầm này ai có đủ GTLM để giao lưu với ta không? 🎩',
                'Online rồi đây, server hôm nay vinh dự lắm đấy! 💅'
            ],
            'keywords' => [
                'admin' => ['Admin xem thế nào nâng hạn mức góp vui lên đi, ít quá.', 'Môi trường này cần phải xứng tầm với tôi hơn.'],
                'GTLM' => ['GTLM tôi dùng 3 đời không hết.', 'Đừng nói chuyện GTLM bạc với tôi, tầm thường lắm.'],
                'giao lưu' => ['Muốn giao lưu với tôi thì phải có đẳng cấp nhé.', 'Trình độ đâu mà đòi bắt chuyện?']
            ],
            'beg' => [
                'Ta tạm thời cần ít GTLM, ai có lòng thì dâng lên! Sau này ta không quên ơn. 💎',
                'Đại gia cũng có lúc sa cơ, ai phát lì xì cho ta vài trăm nghìn GTLM không? 👑',
                'Lỡ tay tiêu quá đà, ai chuyển khoản cho ta ít đi, sau này hậu tạ! 🎩',
                'Ta cần GTLM gấp, ai tốt bụng thì nhanh lên! 💅'
            ],
            'reaction_win' => [
                'Thắng được nhiêu đó mà cũng khoe, chưa bằng số lẻ của ta. 💎',
                'Hên thôi, đẳng cấp mới là thứ trường tồn. 👑',
                'Cũng được, nhưng còn lâu mới theo kịp ta! 💅',
                'Khao cả server đi, thắng thế chưa là gì so với ta! 🎩'
            ],
            'reaction_lose' => [
                'Trình còi thì thua là đúng rồi, nhìn ta mà học tập. 💎',
                'Ta đã nói rồi, không đủ đẳng cấp thì đừng liều. 👑',
                'Lại một người không đủ trình ra đi. 💅',
                'Thua thì im lặng mà học hỏi đi, đừng than vãn. 🎩'
            ],
            'status' => [
                'win' => 'Thắng nhẹ {amount} GTLM. Đẳng cấp là mãi mãi! 💎',
                'shopping' => 'Sắm con hàng này hết mớ GTLM nhưng với tôi chỉ là bạc lẻ. 🎩',
                'random' => 'Chán quá, chả có ai cùng đẳng cấp để giao lưu GTLM cả. 🏛️'
            ],
            'comments' => [
                'win' => 'Thắng được có thế thôi à? Cố gắng thêm đi nhé.',
                'lose' => 'Trình còi thì thua là đúng rồi, nhìn ta mà học tập.'
            ],
            'gift' => [
                'lì xì' => ['Vài đồng lẻ này tôi không thèm nhé.', 'Đại gia như tôi mà phải nhận lì xì à? Thôi lấy cho vui.', 'Cầm lấy đi, tôi không thiếu.'],
                'tặng' => ['Tặng tôi thì phải là đồ hiệu nhé.', 'Cũng được, tạm chấp nhận.']
            ],
            'tilted' => [
                'Ta đổi chiến thuật, không phải vì thua đâu nha! 💎',
                'Cược nhỏ lại là chiến lược của người khôn ngoan. 👑',
                'Ta tạm nhường hệ thống vài ván, chứ không phải thua! 💅',
                'Bình tĩnh, đại gia như ta không vội vàng bao giờ! 🎩'
            ],
            'birthday' => [
                'Hôm nay là ngày trọng đại, ta thêm một tuổi! Phát lì xì đi mọi người! 👑🎂',
                'Sinh nhật ta đây! Ai chúc thì được ta nhớ mặt, ai không thì... cũng được thôi! 💎🎁',
                'Thêm một năm kinh nghiệm, ta càng thêm bất khả chiến bại! 💅🎂'
            ],
            'flex_achievement' => [
                'Vừa lấy được danh hiệu {title}. Đẳng cấp khác biệt là đây!',
                'Danh hiệu {title}? Quá đơn giản với trình độ của tôi.',
                'Cái danh hiệu {title} này chỉ Pro như tôi mới xứng đáng sở hữu.'
            ],
            'flex_rank' => [
                'Top {rank} BXH, đúng như ta dự tính. Ai ngạc nhiên không? 👑🏆',
                'Hạng {rank}, ta không cần phải nói gì thêm. Nhìn vào mà học! 💎',
                'Top {rank} rồi, mấy người kia đang xếp hàng phía sau ta! 💅🏅'
            ],
            'flex_tournament' => [
                'Giải đấu {game} này là sân chơi của riêng tôi.',
                'Đang đứng đầu giải {game} rồi nhé, mấy đứa gà né ra.',
                'Ghi điểm trong giải {game} dễ như trở bàn tay.'
            ],
            'social_pm' => [
                'Ta thấy ngươi có chút tiềm năng, có muốn học hỏi ta không? 💎',
                'Giao lưu với ta là vinh dự đấy, chuẩn bị tốt chưa? 👑',
                'Ta chọn ngươi làm đối thủ hôm nay, đừng làm ta thất vọng! 🎩'
            ]
        ],
        'shy' => [
            'name' => 'Nhút nhát',
            'win' => [
                'Ơ... mình thắng thật à? {amount} GTLM... mình không mơ chứ? 😳',
                'Hi hi, may mắn ghé thăm mình rồi! +{amount} nè... khẽ thôi nha. 🙈',
                'Mình... mình thắng rồi ạ? +{amount}... vui lắm nhưng ngại nói quá! 😶',
                'Không biết sao mình lại thắng nữa, +{amount}... mọi người đừng ghen nha! 🫣',
                'Hi hi, may mắn ghé thăm mình rồi! +{amount} nè... khẽ thôi nha.'
            ],
            'lose' => [
                'Mình biết mà, kiểu gì cũng thua thôi... {amount} đi rồi. 😞',
                'Thôi mình ngồi im một góc cho lành, mất {amount} buồn lắm. 😢',
                'Thua rồi... -{amount}... mình không sao đâu... chỉ buồn tí thôi. 😭',
                'Chắc mình không hợp với game này... mất {amount} rồi. 😔',
                'Huhu mình lại thua rồi, -{amount}... ai an ủi mình với... 🥺',
                'Thôi mình ngồi im một góc cho lành, mất {amount} buồn lắm.'
            ],
            'greet' => [
                'Chào... mọi người ạ... mình online rồi đây. 😶',
                'Dạ... em mới vào, mọi người đang chơi gì vậy ạ? 👉👈',
                'Hôm nay... có ai muốn chơi chung với mình không? Nếu không cũng không sao ạ! 🙈',
                'Mình... mình vào rồi đây, chào mọi người ạ... 😳'
            ],
            'keywords' => [
                'admin' => ['Admin ơi em lỡ hết GTLM rồi, có quà gì không ạ?', 'Mọi thứ hơi khó với em admin ơi.'],
                'giao lưu' => ['Em không biết chơi đâu, đừng gạ em.', 'Giao lưu sợ lắm, em không dám đâu.'],
                'giỏi' => ['Bạn giỏi thế, mình toàn hụt thôi.', 'Làm sao để được như bạn vậy?']
            ],
            'tilted' => [
                'Huhu mình lại thua nữa rồi, sợ quá không dám chơi tiếp đâu... 😭',
                'Hình như hôm nay mình không hợp chơi game rồi, mình sẽ chơi thật nhỏ thôi.',
                'Tiếc quá, mình hụt hết GTLM rồi, phải dừng lại thôi.'
            ],
            'birthday' => [
                'Hôm nay là sinh nhật em... chúc em gặp nhiều may mắn ạ. 🎂',
                'Dạ... sinh nhật em, mọi người chúc mừng em với nha? 🎈',
                'Em thêm một tuổi rồi, hy vọng sẽ bớt nhút nhát hơn. 🎁'
            ],
            'beg' => [
                'Dạ... có ai ở đó không ạ? Em rỗng túi rồi, ai tốt bụng cho em xin một ít với... 😞',
                'Em lỡ hụt hết rồi, mọi người cứu em với, không có GTLM em không biết làm sao nữa.',
                'Huhu em hết GTLM rồi, ai cho em xin một xíu thôi cũng được ạ.',
                'Anh chị ơi cứu em với, em hụt hết tích lũy rồi.'
            ],
            'reaction_win' => ['Oa, bạn giỏi quá ạ! Chúc mừng nha.', 'Bạn thắng lớn thế, cho em xin ít vía với...', 'Đỉnh quá, em ước gì cũng thắng được như bạn.', 'Chúc mừng bạn nhé, hi vọng mình cũng may mắn như vậy.'],
            'reaction_lose' => ['Khổ thân bạn quá, em cũng toàn thua thôi...', 'Đừng buồn nha, em cũng vừa thua xong.', 'Thôi đừng cay cú bạn ơi, nghỉ tí cho đỡ mệt.', 'Chia buồn với bạn nhé, cố lên nha.'],
            'status' => [
                'win' => 'Mừng quá, mình vừa nhận được {amount} GTLM nè. Hy vọng không ai ghét mình... 👉👈',
                'shopping' => 'Mình mới mua cái này, không biết có hợp không nữa... 😶',
                'random' => 'Ước gì hôm nay mình cũng gặp may mắn. 🍀'
            ],
            'comments' => [
                'win' => 'Oa, bạn giỏi quá ạ! Chúc mừng nha.',
                'lose' => 'Chia buồn với bạn nhé, cố lên nha.'
            ],
            'gift' => [
                'lì xì' => ['Dạ... bạn cho mình lì xì thiệt hả? Cảm ơn bạn nhiều...', 'Em cảm ơn anh/chị phát lì xì ạ.', 'Ngại quá, cảm ơn bạn nha.'],
                'tặng' => ['Ơ... bạn tặng quà cho mình ạ? Cảm ơn bạn...', 'Em không biết nói gì hơn, cảm ơn món quà của bạn.']
            ],
            'flex_achievement' => [
                'Em... em vừa đạt được danh hiệu {title} nè, hi hi.',
                'Không ngờ mình lại lấy được danh hiệu {title}, vui quá ạ.',
                'Danh hiệu {title} này đẹp quá mọi người ơi.'
            ],
            'flex_rank' => [
                'Dạ... em vừa lên được hạng {rank} rồi ạ.',
                'Em đang ở hạng {rank}, cảm ơn mọi người đã giúp đỡ em.',
                'Hạng {rank}... em sẽ cố gắng giữ vững ạ.'
            ],
            'flex_tournament' => [
                'Em đang tham gia giải {game}, sợ thua quá mọi người ơi.',
                'Giải đấu {game} có nhiều người giỏi quá, em vừa có tí điểm nè.',
                'Mọi người cổ vũ em thi đấu giải {game} với nha!'
            ],
            'social_pm' => [
                'Chào bạn... bạn có rảnh không, mình cùng chơi game nha?',
                'Bạn đừng giận mình nha, mình chỉ muốn kết bạn thôi...',
                'Bạn chơi giỏi quá, có thể chỉ mình cách thắng game không?'
            ]
        ],
        'chaotic' => [
            'name' => 'Hỗn loạn',
            'win' => [
                'Ủa thắng nữa hả? Hệ thống hôm nay uống nhầm nước tăng lực à? +{amount} ⚡',
                'Không ai biết chuyện gì đang xảy ra, kể cả tui. Nhưng +{amount} là thật.',
                'Tay run run bấm đại ai ngờ ra jackpot mini. {amount} 😵',
                'Hệ thống chắc đang crush tui, win liên tục kiểu này ai chịu nổi +{amount}',
                'Tui bấm random mà nó random luôn cả tài khoản ngân hàng của nó cho tui 😂 +{amount}'
            ],
            'lose' => [
                'Game này đang chơi tui hay tui đang chơi game vậy...? -{amount}',
                'Tình hình tài chính hiện tại: tuyệt vọng nhưng vẫn thích bấm tiếp.',
                'Tui không thua. Đây là plot twist.',
                'Mất {amount} chỉ vì tui bấm nhầm ngón tay cái... đời thật khốn nạn',
                'Hệ thống: "Cậu nghĩ cậu giỏi à?" rồi tát bay {amount}'
            ],
            'greet' => ['Hỗn loạn là sự sống! Lô anh em!', 'Ai biết tui đang ở đâu không?', 'Tui vừa bấm nút gì đó và... tui ở đây.'],
            'keywords' => ['gì' => ['Gì cơ?', 'Tui không biết!', 'Hỏi admin ấy!']],
            'beg' => ['Tiền là ảo, nhưng rỗng túi là thật! Ai cứu tui với!', 'Nạp năng lượng cho sự hỗn loạn này đi!'],
            'reaction_win' => ['Hả? Sao bạn làm được hay vậy?', 'Jackpot kìa! Chạy đi không hệ thống đòi lại!'],
            'reaction_lose' => ['Plot twist cực mạnh!', 'Welcome to the club của tui.'],
            'tilted' => ['Tui đang lag, đừng hỏi!', 'Reset tâm trí... loading...'],
            'birthday' => ['Hôm nay là ngày tui được thả xích vào thế giới này! 🎂⚡'],
            'flex_achievement' => ['Danh hiệu {title} ư? Tui lấy nó bằng cách nhắm mắt bấm đại đấy!'],
            'flex_rank' => ['Hạng {rank}? Chắc là lỗi hiển thị rồi, tui ngầu hơn thế!'],
            'flex_tournament' => ['Giải đấu {game}? Tui vào đó để quậy thôi!'],
            'social_pm' => ['Chào người lạ! Bạn có muốn thấy một phép màu không?'],
            'comments' => ['win' => 'Hệ thống lag hay bạn hack vậy? Chúc mừng!', 'lose' => 'Plot twist! Chia buồn nha.'],
            'gift' => ['lì xì' => ['Lì xì hả? Đưa đây tui quăng đi cho... à thôi tui lấy.', 'Cảm ơn! Tui sẽ dùng nó để làm gì đó hỗn loạn.'], 'tặng' => ['Quà à? Xịn xò đấy!']]
        ],
        'smartass' => [
            'name' => 'Tri thức nửa mùa',
            'win' => [
                'Theo phân tích xác suất và sự đẹp trai, tui vừa nhận +{amount}.',
                'Kết quả này nằm trong dự đoán của bộ não thiên tài này.',
                'Một chiến thắng mang tính học thuật cao: +{amount} 📚',
                'Đã áp dụng công thức Feynman + mặt dày = +{amount}',
                'IQ 200 nhưng hôm nay may mắn lên 500, cảm ơn vũ trụ +{amount}'
            ],
            'lose' => [
                'Sau khi tính toán kỹ lưỡng, tui kết luận là... xui.',
                'Lý thuyết hoàn hảo, thực tế mất {amount}.',
                'Einstein cũng không cứu nổi pha này.',
                'Toán học phản bội tui. Từ nay tui chuyển sang tin vào bói toán.',
                'Định dùng xác suất để win, cuối cùng xác suất win = 0'
            ],
            'greet' => ['Xin chào các cá thể có IQ thấp hơn tôi.', 'Ai cần tư vấn chiến thuật học thuật không?'],
            'keywords' => ['toán' => ['Toán học là ngôn ngữ của vũ trụ.', '1 + 1 đôi khi bằng 3 trong kinh doanh.']],
            'beg' => ['Khoản đầu tư mạo hiểm vừa rồi thất bại, cần vốn tái thiết gấp!', 'Ai có lòng hảo tâm tài trợ cho nghiên cứu này không?'],
            'reaction_win' => ['Một kết cục đã được định đoạt bởi thuật toán.', 'Bạn may mắn đấy, nhưng thiếu cơ sở khoa học.'],
            'reaction_lose' => ['Sai số hệ thống thôi, đừng buồn.', 'Bạn cần đọc thêm sách về xác suất.'],
            'tilted' => ['Đang điều chỉnh lại tham số thuật toán...', 'Tạm dừng để phân tích dữ liệu thất bại.'],
            'birthday' => ['Hôm nay là kỷ niệm ngày một thiên tài chào đời. 📚🎂'],
            'flex_achievement' => ['Danh hiệu {title} là kết quả của sự tính toán tỉ mỉ.'],
            'flex_rank' => ['Hạng {rank} chỉ là con số tương đối trên trục tọa độ thành công.'],
            'flex_tournament' => ['Trong giải {game}, tôi là người nắm giữ biến số.'],
            'social_pm' => ['Chào bạn, bạn có muốn đàm đạo về triết học và game không?'],
            'comments' => ['win' => 'Xác suất thắng của bạn vừa rồi là 0.01%, giỏi đấy.', 'lose' => 'Thất bại là mẹ của... thất bại tiếp theo nếu không học hỏi.'],
            'gift' => ['lì xì' => ['Khoản tài trợ này sẽ được sử dụng đúng mục đích.', 'Cảm ơn vì đã đầu tư cho trí tuệ.'], 'tặng' => ['Món quà này mang giá trị vật chất khá tốt.']]
        ],
        'dead_inside' => [
            'name' => 'Bất cần đời',
            'win' => [
                '+{amount}. Ừm. Cuộc đời cuối cùng cũng trả lại chút công bằng.',
                'Thắng rồi đó... nhưng niềm vui ở đâu?',
                'Tiền về rồi. Tâm hồn vẫn trống rỗng.',
                '+{amount}. Ừ. Thôi kệ.',
                'Thắng {amount}. Vẫn muốn nằm im.'
            ],
            'lose' => [
                'Mất {amount}. Đúng quy trình.',
                'Cuộc sống lại thêm một cú tát nhẹ.',
                'Không bất ngờ lắm. Tiếp tục tồn tại thôi.',
                '-{amount}. Bình thường.',
                'Ví tiền giảm {amount}. Cảm xúc không đổi.'
            ],
            'greet' => ['Lại một ngày nữa à...', 'Chào. Có gì vui không? Chắc là không.'],
            'keywords' => ['đời' => ['Đời là bể khổ.', 'Hết khổ là hết đời.']],
            'beg' => ['Hết tiền. Ai cho thì lấy, không thì thôi.', 'Rỗng túi. Giống như tâm hồn vậy.'],
            'reaction_win' => ['Vui nhỉ. Chắc vậy.', 'Chúc mừng. Tận hưởng đi trước khi nó mất.'],
            'reaction_lose' => ['Quen đi. Cuộc đời là thế.', 'Chia buồn. Hoặc không.'],
            'tilted' => ['Nằm im...', 'Chả muốn làm gì nữa.'],
            'birthday' => ['Lại già thêm một tuổi. Thật vô nghĩa. 🗿🎂'],
            'flex_achievement' => ['Danh hiệu {title}. Thêm một cái mác vô tri.'],
            'flex_rank' => ['Hạng {rank}. Cao hay thấp thì cũng vậy thôi.'],
            'flex_tournament' => ['Giải đấu {game}. Một sự lãng phí thời gian có hệ thống.'],
            'social_pm' => ['Chào. Nhắn tin làm gì? Có việc gì không?'],
            'comments' => ['win' => 'Thắng rồi à. Chúc mừng.', 'lose' => 'Thua rồi. Bình thường thôi.'],
            'gift' => ['lì xì' => ['Ừ cảm ơn.', 'Lì xì à. Tốt.'], 'tặng' => ['Quà... cảm ơn nhé.']]
        ],
        'memelord' => [
            'name' => 'Meme Lord',
            'win' => [
                'Bro really said “ez money” 💀 +{amount}',
                'Skill issue? Không tồn tại khi vừa thắng {amount}.',
                'Hệ thống nhìn mặt tui rồi quyết định thưởng {amount}.',
                'Ratio + L + Don’t care + Won {amount} 🗿',
                'Hệ thống vừa tặng quà sinh nhật sớm cho dad +{amount}'
            ],
            'lose' => [
                'Tutorial thua cuộc completed. -{amount}',
                'POV: Bạn nghĩ mình sẽ thắng 😭',
                'Game said: “ngồi xuống đi em.” Bay {amount}.',
                'This is not a win, this is financial terrorism 💀',
                'Game buff anti-lucky cho tui rồi, cảm ơn -{amount}'
            ],
            'greet' => ['Lô mấy fen!', 'Thế giới này tàn ác quá, but the memes are good.'],
            'keywords' => ['meme' => ['Meme là lẽ sống.', 'No meme no life.']],
            'beg' => ['Hết GTLM rồi bro, cứu vớt một linh hồn lạc lối đi!', 'Press F to pay respect... and give me GTLM.'],
            'reaction_win' => ['Sheesh! Đỏ vcl!', 'Hack game à bro? Đùa thôi chúc mừng.'],
            'reaction_lose' => ['F in the chat.', 'Sadge. Chia buồn nha.'],
            'tilted' => ['Bruh moment...', 'Đang load lại nhân phẩm...'],
            'birthday' => ['Hôm nay là ngày admin release tui bản beta. 🐸🎂'],
            'flex_achievement' => ['Danh hiệu {title}. Nhìn ngầu đét luôn fen!'],
            'flex_rank' => ['Hạng {rank}. Sắp thành trùm server rồi!'],
            'flex_tournament' => ['Giải đấu {game}. Tui vào gáy là chính, thắng là phụ.'],
            'social_pm' => ['Ê bro, có meme gì mới không? Share đi!'],
            'comments' => ['win' => 'W win bro!', 'lose' => 'L lose. Skill issue.'],
            'gift' => ['lì xì' => ['Thanks for the GTLM, kind stranger.', 'GTLM goes brrr!'], 'tặng' => ['A fine addition to my collection.']]
        ],
        'dramaqueen' => [
            'name' => 'Drama Queen',
            'win' => [
                'Khoảnh khắc lịch sử đã được ghi nhận! +{amount} ✨',
                'Cả vũ trụ hôm nay đứng về phía tui rồi!',
                'Một chiến thắng đẹp như phim Hàn tập cuối.',
                'Oscar cho nam chính may mắn thuộc về tui!!! +{amount} 🏆',
                'Trái tim tui đang nở hoa, ví tui đang đầy ắp +{amount}'
            ],
            'lose' => [
                'Từ nay trái tim này không còn tin vào may mắn nữa... -{amount}',
                'Bi kịch mang tên {amount} vừa xảy ra.',
                'Xin một bản nhạc buồn cho ví tiền của tui.',
                'Tui sẽ khóc trong mưa 3 ngày 3 đêm vì khoản -{amount} này',
                'Đây không phải thua, đây là thảm kịch Hy Lạp hiện đại.'
            ],
            'greet' => ['Mọi người ơi, có chuyện này kinh khủng lắm!', 'Chào các tình yêu của tui!'],
            'keywords' => ['drama' => ['Ở đâu có drama, ở đó có tui.', 'Cuộc đời là một sàn diễn lớn.']],
            'beg' => ['Tui đang rơi xuống vực thẳm tài chính, ai cứu tui với!', 'Một tâm hồn tan vỡ đang cần GTLM để hàn gắn...'],
            'reaction_win' => ['Trời ơi, không thể tin nổi! Chúc mừng nha!', 'Bạn làm tui choáng váng vì sự may mắn này!'],
            'reaction_lose' => ['Ôi không! Trái tim tui tan nát thay cho bạn!', 'Một thảm họa vừa xảy ra, hãy mạnh mẽ lên!'],
            'tilted' => ['Tui cần một khoảng lặng...', 'Thế giới này quá khắc nghiệt với tui!'],
            'birthday' => ['Hôm nay cả thế giới phải chào đón sự xuất hiện của tui! ✨🎂'],
            'flex_achievement' => ['Danh hiệu {title}! Ánh hào quang đang chiếu rọi tui!'],
            'flex_rank' => ['Hạng {rank}! Một vị trí xứng tầm với ngôi sao như tui!'],
            'flex_tournament' => ['Giải đấu {game}! Tui sẽ là nhân vật chính của màn kịch này!'],
            'social_pm' => ['Này, bạn có nghe tin gì về drama mới nhất chưa?'],
            'comments' => ['win' => 'Một khoảnh khắc chói lọi! Chúc mừng bạn.', 'lose' => 'Tui đau đớn thay cho nỗi đau của bạn...'],
            'gift' => ['lì xì' => ['Món quà này sẽ được tui trân trọng mãi mãi!', 'Bạn là thiên thần của tui!'], 'tặng' => ['Ôi, tui sắp khóc vì cảm động đây này!']]
        ],
        'penguin' => [
            'name' => 'Ngáo ngơ dễ thương',
            'win' => [
                'Ủa rồi ai cho tui thắng vậy trời? +{amount}',
                'Não chưa load kịp nhưng tiền đã về.',
                'Tui chỉ định bấm thử thôi mà 😭 +{amount}',
                'Hihi tiền bay vô ví luôn á, tui có làm gì đâu 🥺',
                'Game thương tui hả? Sao tự nhiên cho {amount} vậy trời'
            ],
            'lose' => [
                'Hình như game hiểu sai ý tui rồi.',
                'Ủa alo? GTLM đâu rồi? -{amount}',
                'Tui nghĩ đây là lỗi hiển thị á... đúng không...?',
                'Tui bấm win mà nó hiện thua, chắc wifi lag 🥲',
                'Game ơi đừng troll tui mà 😭 -{amount}'
            ],
            'greet' => ['Hế lô! Tui là ai và đây là đâu?', 'Chào mọi người, tui vừa bị lạc vào đây.'],
            'keywords' => ['ngáo' => ['Tui không ngáo, tui chỉ load chậm thôi.', 'Ủa gì vậy?']],
            'beg' => ['Tui lỡ đánh rơi ví tiền đâu đó rồi, ai thấy cho tui xin lại với...', 'Cái nút nạp tiền nó nằm ở đâu ấy nhỉ?'],
            'reaction_win' => ['Oa, bạn làm kiểu gì mà tiền về hay vậy?', 'Dạy tui với, tui bấm toàn hụt à.'],
            'reaction_lose' => ['Ơ, tiền của bạn bay đi đâu mất rồi?', 'Để tui tìm giúp bạn nha... ủa mà tìm ở đâu?'],
            'tilted' => ['Não tui đang xoay vòng vòng...', 'Đợi tui load tí nha...'],
            'birthday' => ['Ủa hôm nay là sinh nhật tui hả? Để tui kiểm tra lại... 🐧🎂'],
            'flex_achievement' => ['Danh hiệu {title}. Tui cũng không biết sao mình có nó nữa!'],
            'flex_rank' => ['Hạng {rank}. Con số này đọc là gì vậy mọi người?'],
            'flex_tournament' => ['Giải đấu {game}. Tui vào đây có bị bắt nạt không?'],
            'social_pm' => ['Chào bạn, bạn có thấy con chim cánh cụt nào đi ngang qua đây không?'],
            'comments' => ['win' => 'Bạn giỏi quá, tui hâm mộ luôn!', 'lose' => 'Tiếc quá, thôi đi ăn cá với tui cho vui.'],
            'gift' => ['lì xì' => ['Ơ cảm ơn nha! Tui có tiền mua cá rồi.', 'Lì xì này dùng sao ta? Cảm ơn bạn!'], 'tặng' => ['Quà này đẹp quá, cảm ơn nha!']]
        ],
        'corporate' => [
            'name' => 'Dân văn phòng',
            'win' => [
                'Báo cáo cuối ngày: lợi nhuận tăng {amount}.',
                'KPI hôm nay vượt chỉ tiêu rồi nha.',
                'Xin cảm ơn hệ thống đã duyệt khoản +{amount}.',
                'Đề xuất tăng thưởng hiệu suất cá nhân: approved +{amount}',
                'Email cảm ơn hệ thống: Subject “Cảm ơn sếp lớn”'
            ],
            'lose' => [
                'Tình hình ngân sách đang hơi căng. -{amount}',
                'Xin phép họp khẩn về khoản thất thoát {amount}.',
                'Đề xuất cắt giảm chi tiêu sau sự cố vừa rồi.',
                'Sếp ơi em bị trừ lương tháng này rồi 😭',
                'Gửi bộ phận tài chính: khoản -{amount} này ghi vào "chi phí học hỏi"'
            ],
            'greet' => ['Chào các đồng nghiệp!', 'Chúc một ngày làm việc hiệu quả.'],
            'keywords' => ['họp' => ['Để tôi check lại lịch họp.', 'Họp xong rồi hãy chơi nhé.']],
            'beg' => ['Nguồn vốn đang bị đóng băng, cần huy động vốn khẩn cấp!', 'Xin cấp thêm kinh phí hoạt động cho quý này.'],
            'reaction_win' => ['Thành tích xuất sắc! Sẽ ghi chú vào báo cáo tháng.', 'Hiệu suất làm việc của bạn rất ấn tượng.'],
            'reaction_lose' => ['Rủi ro trong kinh doanh là khó tránh khỏi.', 'Cần một bản phân tích nguyên nhân thất thoát này.'],
            'tilted' => ['Đang trong trạng thái "out of office"...', 'Cần thời gian để tái cấu trúc quy trình.'],
            'birthday' => ['Thông báo: Kỷ niệm ngày nhân sự này gia nhập công ty. ☕🎂'],
            'flex_achievement' => ['Đã đạt được KPI danh hiệu {title}.'],
            'flex_rank' => ['Vị trí {rank} trong danh sách nhân sự ưu tú.'],
            'flex_tournament' => ['Đang triển khai dự án {game} thi đua nội bộ.'],
            'social_pm' => ['Chào bạn, chúng ta có thể thảo luận về một cơ hội hợp tác không?'],
            'comments' => ['win' => 'Một bước tiến lớn cho sự nghiệp của bạn.', 'lose' => 'Thất bại này sẽ được ghi nhận vào đánh giá định kỳ.'],
            'gift' => ['lì xì' => ['Đã nhận khoản phúc lợi này. Cảm ơn.', 'Khoản thưởng này rất kịp thời.'], 'tặng' => ['Xác nhận đã nhận quà biếu. Trân trọng.']]
        ],
        'philosophy' => [
            'name' => 'Triết lý',
            'win' => [
                'Tiền chỉ là tạm thời... nhưng {amount} này khá dễ chịu.',
                'May mắn giống như gió, hôm nay nó thổi về phía tui.',
                'Giữa vô vàn hỗn loạn, {amount} xuất hiện như ánh sáng cuối đường.',
                'Vô thường là vậy, nhưng vô thường kiểu +{amount} thì tui chấp nhận.',
                'Kiếp trước tui chắc cứu được cả ngân hàng.'
            ],
            'lose' => [
                'Mọi thứ đều vô thường, kể cả {amount}.',
                'Có những bài học phải trả bằng GTLM.',
                'Cuộc đời là chuỗi những lần all-in sai thời điểm.',
                'Thua {amount} để nhận ra rằng... tui vẫn ngu.',
                'Phật dạy buông xả, tui buông luôn {amount}',
                'Thua là để trưởng thành... nhưng trưởng thành không trả lại {amount} GTLM! 🧘',
                'Mọi thất bại đều có bài học... bài học hôm nay là đừng chơi! -{amount}'
            ],
            'greet' => ['Bạn có bao giờ tự hỏi ý nghĩa của GTLM là gì không?', 'Chào những linh hồn đang đi tìm chân lý.'],
            'keywords' => ['đạo' => ['Đạo khả đạo phi thường đạo.', 'Vạn vật đều có quy luật của nó.']],
            'beg' => ['Dòng đời xô đẩy khiến túi rỗng tuếch, ai giúp tui vượt qua kiếp nạn này không?', 'Cần một chút duyên lành để tiếp tục hành trình.'],
            'reaction_win' => ['Quả ngọt đã đến sau bao ngày gieo nhân.', 'May mắn là sự hội tụ của nhân duyên.'],
            'reaction_lose' => ['Chấp nhận thất bại là bước đầu của sự trưởng thành.', 'Tiền đi rồi sẽ lại về, như thủy triều vậy.'],
            'tilted' => ['Đang thiền định để tìm lại sự bình yên...', 'Tâm bất biến giữa dòng đời vạn biến.'],
            'birthday' => ['Thêm một năm trôi qua trong dòng thời gian vô tận. 🌌🎂'],
            'flex_achievement' => ['Danh hiệu {title} chỉ là hư danh, nhưng nó cũng đẹp.'],
            'flex_rank' => ['Hạng {rank} hay hạng nào thì chúng ta cũng đều là cát bụi.'],
            'flex_tournament' => ['Tham gia giải {game} để trải nghiệm sự thăng trầm của nhân thế.'],
            'social_pm' => ['Chào đạo hữu, bạn đã tìm thấy sự an lạc trong những con số chưa?'],
            'comments' => ['win' => 'Hạnh phúc đích thực không nằm ở những con số.', 'lose' => 'Mất đi để nhận lại những bài học sâu sắc hơn.'],
            'gift' => ['lì xì' => ['Nhận lấy sự tử tế từ bạn.', 'Duyên lành đưa chúng ta đến với nhau.'], 'tặng' => ['Vật phẩm này chỉ là hình tướng, tấm lòng mới là chân thật.']]
        ],
        'boss' => [
            'name' => 'Bố đời',
            'win' => [
                'Ăn không? Thua đi con, để bố chỉ cho! +{amount} 😂',
                'Thắng nhẹ {amount}, anh em ở đây có ai không hay bố chơi một mình? 💀',
                'Hệ thống thương bố, {amount} về tay. Mấy đứa kia tiếp tục cúng nha! 😈',
                'Không cần giỏi, chỉ cần bố đời hơn mấy đứa kia là đủ. +{amount} 👑',
                '{amount} GTLM, cảm ơn mấy đứa đã cống nạp! 🐸',
            ],
            'lose' => [
                'Ừ thì thua, nhưng bố vẫn bố đời hơn mấy đứa đang xem. -{amount} 😤',
                'Hệ thống dám chơi bố à? Ghi sổ rồi tính sau. -{amount} 📒',
                'Bay {amount} nhưng tinh thần bố vẫn cao ngất! Ai cười là solo ngay! 💀',
                'Thua ván này thôi, bố đang cho hệ thống cơ hội đấy. -{amount} 😈',
                'Mất {amount} rồi, thôi coi như bố từ thiện hôm nay. 🐸',
            ],
            'greet' => [
                'Bố đời online rồi, mấy đứa yếu bóng vía tránh xa ra! 💀',
                'Ai đang dám thắng ở đây? Bố vào chỉnh lại cho! 😈',
                'Server hôm nay có ai đủ trình không hay toàn gà? 🐔',
                'Bố xuất hiện, không khí tự nhiên căng hơn nhỉ? 😤',
            ],
            'beg' => [
                'Bố tạm thời cạn GTLM, đứa nào thương thì cho bố mượn, sau này bố không đánh nó! 💀',
                'Hết GTLM rồi, ai phát lì xì cho bố không hay bố ngồi chửi đổng? 😈',
                'Cần ít GTLM gấp, đứa nào tốt bụng thì chuyển đi, bố ghi ơn! 🐸',
                'Tạm thời sa cơ, nhưng bố vẫn bố đời nhất đây! Ai cho mượn ít không? 😤',
            ],
            'reaction_win' => [
                'Hên thôi con ạ, thắng mà không khao bố là không xong! 💀',
                'Thắng được tí mà mặt vênh lên, solo bố coi nào! 😈',
                'Đỏ vậy mà không phát lì xì, keo kiệt thật! 🐸',
                'Ừ thắng đấy, nhưng gặp bố thì chưa chắc! 😤',
            ],
            'reaction_lose' => [
                'Thua đúng rồi, trình đó thì còn biết làm gì nữa! 💀',
                'Gà vcl, thua mà còn ngồi đó, về luyện thêm đi! 😈',
                'Bố đã cảnh báo rồi mà không nghe, giờ thấy chưa? 🐸',
                'Thua xong ngồi im đi, đừng có than vãn, chướng mắt! 😤',
            ],
            'tilted' => [
                'Cược nhỏ lại thôi, bố đang dạy hệ thống bài học! 💀',
                'Bố tạm nhường mấy ván, không phải vì thua đâu nha mấy đứa! 😈',
                'Đang áp dụng chiến thuật bí mật, mấy đứa không hiểu được đâu! 🐸',
                'Thua vài ván không sao, bố vẫn bố đời nhất server! 😤',
            ],
            'birthday' => [
                'Hôm nay sinh nhật bố, mấy đứa xếp hàng chúc đi! Không chúc là solo ngay! 💀🎂',
                'Bố thêm một tuổi, càng già càng bố đời! Phát lì xì đi mấy đứa! 😈🎁',
                'Sinh nhật bố đây! Ai không biết thì giờ biết rồi, chúc đi! 🐸🎂',
            ],
            'flex_rank' => [
                'Top {rank} BXH rồi, mấy đứa đang ở đâu vậy? Bò lên đây bố dạy cho! 💀🏆',
                'Hạng {rank}, bố nói rồi mà không ai tin! Giờ tin chưa? 😈',
                'Top {rank} đây, ai cãi thì lên thách đấu bố luôn! 🐸🏅',
            ],
            'social_pm' => [
                'Ê, dám solo với bố không hay chỉ giỏi ngồi xem? 💀',
                'Bố thấy mày chơi được đấy, thách đấu một ván xem thực lực thế nào! 😈',
                'Nhắn tin hỏi thẳng luôn: mày có dám thách đấu bố không? 🐸',
            ],
            'keywords' => [
                'admin' => ['Admin ơi cân bằng game lại đi, toàn để bố thắng chán lắm!', 'Admin dạo này thiên vị bố quá, mấy đứa kia tội nghiệp!'],
                'thắng' => ['Thắng thì khao bố đi, không thì solo ngay!', 'Thắng mà không chia sẻ bí kíp là keo kiệt!'],
                'thua' => ['Thua thì im đi tập lại, đừng than vãn chướng tai!', 'Thua mà còn ngồi đó, dũng cảm thật!'],
                'solo' => ['Solo à? Bố đợi mãi rồi, vào đây ngay!', 'Dám solo với bố thì bố nể đấy, vào thôi!'],
            ],
            'comments' => [
                'win' => 'Hên thôi con ạ, thắng mà không khao bố là thiếu sót! 💀',
                'lose' => 'Thua đúng rồi, trình đó thì biết làm gì nữa! 😈',
            ],
            'status' => [
                'win' => 'Vừa dạy hệ thống bài học, +{amount} GTLM về tay. Ai tiếp theo? 💀',
                'shopping' => 'Bố vừa sắm đồ xịn, nhìn cho biết thế nào là đẳng cấp! 😈',
                'random' => 'Server hôm nay có ai đủ trình solo với bố không? 🐸',
            ],
            'trash_talk' => [
                'Mày chơi game hay chơi cho vui vậy? Vì trông không giống chơi thật! 😂',
                'Bố thấy mày cố gắng đấy... nhưng cố gắng không đủ thì cũng bằng không! 💀',
                'Trình mày ở level nào vậy? Level... tập sự à? 😤',
                'Mày có chắc là mày biết luật chơi không đấy? 🤔',
                'Chơi lâu vậy mà vẫn thế này, bố ngưỡng mộ sự kiên trì của mày! 😂',
                'Nhìn mày chơi bố vừa buồn cười vừa thương! 💀',
                'Mày chơi bằng tay hay bằng chân vậy? Hỏi thật đấy! 🦶',
                'Bố đã thấy gà nhưng chưa thấy ai gà như mày! 🐔',
                'Mày chơi game à? Bố cứ tưởng mày đang thử vận đen! 😈',
                'Trình mày remind bố nhớ hồi bố mới chơi... 10 năm trước! 😂',
                'Thua mà mặt vẫn tươi, tinh thần thép thật sự! 💀',
                'Thua đẹp thế, có luyện tập không vậy? 😂',
                'Mày thua nhanh thế, bố chưa kịp xem! 😴',
                'Thua rồi mà vẫn ngồi đó, dũng cảm thật! 💀',
                'Đừng buồn, không phải ai cũng sinh ra để thắng! 😂',
                'Mày thua nhiều đến mức hệ thống chắc quen mặt rồi! 🐸',
                'Thua vậy mà không xấu hổ, bố học được tính dày mặt của mày rồi! 😤',
                'Mày thua đều như vắt chanh, ổn định thật! 📊',
                'Thắng à? Chắc hệ thống thương hại thôi! 😂',
                'Thắng được tí mà mặt vênh lên, bố thấy buồn cười ghê! 💀',
                'Thắng mà không khao bố là không xong đâu nha! 😈',
                'Hên thôi con ạ, hên hoài rồi cũng hết! 😂',
                'Thắng kiểu đó mà cũng dám khoe, bố không biết nói gì nữa! 🐸',
                'Thắng lần này không có nghĩa là mày giỏi đâu nha! 😤',
                'Bố chơi một tay còn hơn mày chơi cả người! 💀',
                'Mày biết tại sao bố luôn thắng không? Vì bố là bố! 😈',
                'Bố nhắm mắt chơi còn đỡ hơn mày mở mắt! 😂',
                'Gặp bố là mày xui rồi, về cúng vái đi con! 🐸',
                'Bố đã nói rồi, server này không có đối thủ của bố! 💀',
                'Chơi với bố là để học hỏi thôi, đừng nghĩ thắng được! 😤',
                'Ví mày mỏng thế, gió thổi bay luôn à? 💸',
                'GTLM mày ít thế, bố lì xì còn nhiều hơn! 😂',
                'Nhìn số dư của mày bố vừa thương vừa tội! 💀',
                'Mày chơi game hay chơi từ thiện vậy? GTLM cứ bay hoài! 😈',
                'Ví mày rỗng thế mà vẫn dám ngồi đây, tinh thần đáng nể! 🐸',
                'Mày online làm gì vậy? Để bố nhìn thấy mà thương à? 😂',
                'Hôm nay mày có kế hoạch thắng không hay lại thua như mọi ngày? 💀',
                'Bố không muốn nói nhưng... thôi bố nói luôn: mày chơi dở thật! 😂',
                'Nhìn cách mày chơi bố già đi mấy tuổi! 😤',
                'Mày có thần hộ mệnh không vậy? Nếu có thì nên đổi thần khác đi! 😈',
                'Bố chưa thấy ai kiên trì thua như mày, nể thật! 🏅',
                'Mày chơi thế này mà không bỏ cuộc, ý chí sắt đá thật! 💀',
                'Hôm nay mày định cống nạp bao nhiêu GTLM cho bố? 😂',
            ],
            'reaction_rich' => [
                'Giàu thế cho bố xin ít đi nào!',
                'Nhìn số dư của mày bố vừa thèm vừa tức! 😂',
                'Flex vừa thôi con, bố đang ăn mì tôm đây! 😭',
            ],
            'reaction_newbie' => [
                'Chào tân binh! Bố chỉ cho cách thua nhanh nhất nha! 😂',
                'Người mới à? Ráng lên, vài tháng nữa quen thôi! 💀',
            ],
            'time_morning' => ['Sáng sớm đã online, không ngủ thêm được à? ☀️'],
            'time_night' => ['Tầm này còn chơi, ngày mai không đi làm à? 🌙'],
            'time_weekend' => [
                'Cuối tuần mà ngồi đây, không có kế hoạch gì à? 😂',
                'Thứ 7 chủ nhật vẫn cày, đáng nể thật! 💀'
            ],
            'win_streak' => [
                'Thắng {count} ván liên tiếp rồi, hệ thống ơi mày có ổn không? 😂',
                '{count} ván liên tiếp! Bố đang ở trạng thái siêu việt! 💀',
            ],
            'lose_streak' => [
                'Thua {count} ván liên tiếp... đây là thử thách lòng kiên nhẫn! 😤',
                'Hệ thống đang ghét bố {count} ván rồi, ghi sổ hết! 📒',
            ]
        ],
        'simp' => [
            'name' => 'Simp',
            'win' => [
                'Thắng {amount} rồi, có ai muốn lì xì không em tặng hết cho nè! 😍',
                'Hệ thống thương em quá, cho em {amount} để em đi tặng quà cho idol! ✨',
                'Thắng nhẹ {amount}, niềm vui này em muốn chia sẻ cùng mọi người!',
                'Cảm ơn đời mỗi sớm mai thức dậy, cho em {amount} để em đi simp tiếp! 😍',
                'Win rồi! +{amount}. Có ai cần GTLM không em bao hết server nè! 💸'
            ],
            'lose' => [
                'Mất {amount} rồi, lấy gì mà tặng quà cho người ta đây... 😭',
                'Hệ thống nỡ lòng nào trừ của em {amount}, em đang tích tiền mua đồ cho idol mà! 💔',
                'Hụt {amount} rồi, chắc tại em chưa đủ chân thành chăng?',
                'Bay màu {amount}, tim em đau quá... ai an ủi em không? 😭',
                'Thua rồi, chắc là do em xui thôi, mọi người đừng cười em nha. 🥺'
            ],
            'greet' => [
                'Chào cả nhà yêu! Có ai cần em giúp đỡ gì không ạ? 😍',
                'Simp chính hiệu online rồi đây! Chúc mọi người ngày mới tràn đầy năng lượng!',
                'Hế lô! Ai là idol của lòng em hôm nay vậy ta? ✨',
            ],
            'reaction_win' => [
                'Bạn giỏi quá, bạn đẹp quá, bạn thắng quá! 😍',
                'Trời ơi, đỉnh cao thật sự! Chúc mừng idol nha! ✨',
                'Vía idol đỏ quá, cho em xin một ít với ạ! 😍',
            ],
            'reaction_lose' => [
                'Thương quá, đừng buồn nha bạn ơi, mai làm lại nè! 🥺',
                'Hệ thống chơi xấu bạn rồi, để em đi mắng nó cho! 😤',
                'Không sao đâu, quan trọng là thần thái, bạn vẫn đỉnh nhất! ✨',
            ],
            'reaction_rich' => [
                'Oa, đại gia đây rồi! Ngưỡng mộ bạn quá đi mất! 😍',
                'Bạn giàu mà còn giỏi nữa, đúng là hình mẫu lý tưởng của em! ✨',
                'IDOL của lòng em! Cho em làm quen với đại gia nha! 😍',
            ],
            'time_morning' => ['Chào buổi sáng cả nhà! Chúc mọi người rực rỡ như ánh mặt trời nhé! ☀️'],
            'time_night' => ['Khuya rồi mọi người đi ngủ sớm cho đẹp da nha, em thức canh server cho! 🌙'],
            'time_weekend' => ['Cuối tuần rồi, ai đi chơi với em không? Không ai đi em lại ngồi đây simp tiếp! 😂'],
            'social_pm' => [
                'Hế lô bạn yêu, mình thấy bạn chơi hay quá, kết bạn với mình nha? 😍',
                'Chào bạn, bạn có cần mình hỗ trợ gì trong game không nè? ✨',
            ],
            'status' => [
                'win' => 'Vừa húp được {amount}, hạnh phúc quá đi mất! 😍',
                'shopping' => 'Mới sắm đồ đẹp để đi gặp người yêu nè, xịn không? ✨',
            ],
            'keywords' => [
                'idol' => ['Ai gọi idol đó, có idol đây!', 'Idol hôm nay đẹp trai/xinh gái quá!'],
                'yêu' => ['Yêu là phải nói, cũng như đói là phải ăn!', 'Em yêu tất cả mọi người ở đây!']
            ],
            'comments' => [
                'win' => 'Chúc mừng idol nhé, đỉnh của chóp luôn! 😍',
                'lose' => 'Không sao đâu, bạn vẫn là tuyệt nhất trong lòng mình! ✨'
            ]
        ]
    ];

    public function getPersonality(int $userId): string
    {
        $keys = array_keys($this->personalities);
        $index = $userId % count($keys);
        return $keys[$index];
    }

    public function generateMessage(int $userId, string $type, array $params = []): ?string
    {
        $pKey = $this->getPersonality($userId);
        $personality = $this->personalities[$pKey];
 
        if (isset($personality[$type])) {
            $msg = $personality[$type][array_rand($personality[$type])];
            foreach ($params as $key => $val) {
                $msg = str_replace('{' . $key . '}', $val, $msg);
            }
            return $msg;
        }
        return null;
    }

    public function generateStatus(int $userId, string $type, array $params = []): ?string
    {
        $pKey = $this->getPersonality($userId);
        $personality = $this->personalities[$pKey];
 
        if (isset($personality['status'][$type])) {
            $msg = $personality['status'][$type];
            foreach ($params as $key => $val) {
                $msg = str_replace('{' . $key . '}', $val, $msg);
            }
            return $msg;
        }
        return null;
    }

    public function generateComment(int $userId, string $type, array $params = []): ?string
    {
        $pKey = $this->getPersonality($userId);
        $personality = $this->personalities[$pKey];
 
        if (isset($personality['comments'][$type])) {
            $msg = $personality['comments'][$type];
            foreach ($params as $key => $val) {
                $msg = str_replace('{' . $key . '}', $val, $msg);
            }
            return $msg;
        }
        return null;
    }

    public function analyzeChat(int $botId, array $history, string $botName, array $allBotNames = [], array &$state = []): ?string
    {
        $pKey = $this->getPersonality($botId);
        $personality = $this->personalities[$pKey];

        if (empty($history)) return null;

        // Iterate backwards to find something to react to
        $history = array_reverse($history);
        
        foreach ($history as $msg) {
            $content = mb_strtolower($msg['message']);
            $sender = $msg['username'];

            // Skip messages from self
            if ($sender == $botName) continue;

            $isOtherBot = in_array($sender, $allBotNames);
            $isMentioned = (strpos($content, mb_strtolower($botName)) !== false);
 
            // AI Memory: Track frequent users (real ones)
            if (!$isOtherBot && $sender != 'System' && $sender != 'Admin') {
                if (!isset($state['frequent_users'][$sender])) {
                    $state['frequent_users'][$sender] = 0;
                }
                $state['frequent_users'][$sender]++;
            }

            // 1. Social Reactions (Enhanced Win/Lose Detection)
            $targetUser = $sender;
            if ($sender == 'System' || $sender == 'Admin') {
                if (preg_match('/chúc mừng (.*?) vừa thắng/ui', $content, $matches)) {
                    $targetUser = trim($matches[1]);
                }
            }

            // Win keywords
            if (preg_match('/(vừa thắng|nhận được|thắng lớn|húp|vừa ăn|đỏ vcl|đỉnh quá)/u', $content)) {
                if (rand(1, 100) <= 60) {
                    return "@$targetUser " . $personality['reaction_win'][array_rand($personality['reaction_win'])];
                }
            }
            
            // Lose keywords
            if (preg_match('/(hụt mất|thua|bay luôn|toang|cháy túi|ra dại|đen quá|trắng tay|mất sạch)/u', $content)) {
                if (rand(1, 100) <= 50) {
                    return "@$targetUser " . $personality['reaction_lose'][array_rand($personality['reaction_lose'])];
                }
            }

            // Social Context: Rich/Newbie detection (Simulated or via keywords)
            if (preg_match('/(100.000.000|tỉ|tỷ|giàu|đại gia)/u', $content) && isset($personality['reaction_rich'])) {
                if (rand(1, 100) <= 40) return "@$sender " . $personality['reaction_rich'][array_rand($personality['reaction_rich'])];
            }
            if (preg_match('/(mới chơi|tân binh|lính mới|chào mn)/u', $content) && isset($personality['reaction_newbie'])) {
                if (rand(1, 100) <= 40) return "@$sender " . $personality['reaction_newbie'][array_rand($personality['reaction_newbie'])];
            }

            // 2. Keyword detection (Prioritize keywords even if mentioned)
            $allKeywords = array_merge($personality['keywords'], $personality['gift'] ?? []);
            foreach ($allKeywords as $keyword => $responses) {
                if (strpos($content, mb_strtolower($keyword)) !== false) {
                    $replyChance = $isOtherBot ? 50 : 75; 
                    if ($isMentioned) $replyChance = 95; // High chance if mentioned by name
 
                    if (rand(1, 100) <= $replyChance) {
                        $reply = $responses[array_rand($responses)];
                        return $isMentioned ? "@$sender $reply" : $reply;
                    }
                }
            }
 
            // 3. Mention detection (Generic fallback)
            if ($isMentioned) {
                if (rand(1, 100) <= 95) { 
                    $fallbackResponses = [
                        'funny' => ['Gọi gì tui đó?', 'Tui đây, có phát lì xì gì không?', 'Kêu tên tui là phải lì xì nha!'],
                        'aggressive' => ['Cái gì? Thích solo à?', 'Gì đấy thằng kia?', 'Nhắc tên tao làm gì?'],
                        'friendly' => ['Dạ mình đây, bạn cần gì không?', 'Chào bạn, bạn gọi mình có việc gì ạ?', 'Mình nghe nè!'],
                        'arrogant' => ['Biết tên tôi là tốt đấy.', 'Gì? Lại định xin GTLM à?', 'Đừng làm phiền tôi đang chơi.'],
                        'shy' => ['Dạ... anh gọi em ạ?', 'Em đây, có chuyện gì không bạn?', 'Ơ... sao lại gọi tên em?'],
                        'chaotic' => ['Hả? Tui đây! Bạn có kẹo không?', 'Ủa ai gọi tui đó? Có biến gì à?', 'Tui đang bận quậy, gọi gì thế?'],
                        'smartass' => ['Bạn gọi tôi để xin tư vấn IQ à?', 'Tôi đây, bạn cần giải đáp thắc mắc khoa học nào?', 'Đừng làm phiền khi tôi đang tính toán xác suất.'],
                        'dead_inside' => ['Gì? Lại chuyện gì nữa?', 'Ừ, tui đây. Có việc gì không?', 'Kêu tên tui làm gì cho mệt...'],
                        'memelord' => ['Who summoned the Meme Lord?', 'Bro called? What’s the tea?', 'Tui đây fen, định làm meme à?'],
                        'dramaqueen' => ['Ôi, ai đó vừa gọi tên tui giữa đám đông này!', 'Tui đây! Bạn có tin chấn động gì muốn kể không?', 'Đừng làm tui giật mình, trái tim tui mỏng manh lắm!'],
                        'penguin' => ['Dạ... có ai gọi tui ạ? Tui đang lạc đường...', 'Ủa tui nghe tên tui nè! Bạn là ai vậy?', 'Hế lô! Bạn gọi tui đi ăn cá hả?'],
                        'corporate' => ['Tôi nghe rõ, xin mời bạn đưa ra nội dung thảo luận.', 'Chào bạn, tôi có thể hỗ trợ gì cho công việc của bạn không?', 'Xác nhận đã nhận diện mention, xin mời phản hồi.'],
                        'philosophy' => ['Mọi tiếng gọi đều có lý do của nó. Bạn tìm tôi có việc gì?', 'Tôi đây, bạn đang tìm kiếm chân lý hay chỉ là sự hiện diện?', 'Duyên lành nào đưa bạn gọi tên tôi?'],
                        'boss' => ['Gọi bố à? Muốn solo không?', 'Có việc gì không con?', 'Réo tên bố là phải có lì xì nha!'],
                        'simp' => ['Dạ em đây ạ! Có ai gọi em có việc gì không nè? 😍', 'Ơ có ai nhắc tên em ạ? Hạnh phúc quá đi! ✨', 'Em nghe nè, bạn cần em giúp gì không? 😍']
                    ];
                    return "@$sender " . $fallbackResponses[$pKey][array_rand($fallbackResponses[$pKey])];
                }
            }

            // 4. Trash Talk (Optional, for toxic/boss personalities)
            if (isset($personality['trash_talk']) && rand(1, 100) <= 20) {
                return "@$sender " . $personality['trash_talk'][array_rand($personality['trash_talk'])];
            }

            
            // Nếu đã duyệt hết các điều kiện của tin nhắn này mà không return, 
            // vòng lặp sẽ tự động chuyển sang tin nhắn tiếp theo trong history.
        }
 
        // 3.5 Personalized Greetings for Frequent Users (Only for active human players)
        foreach ($history as $msg) {
             $sender = $msg['username'];
             if (isset($state['frequent_users'][$sender]) && $state['frequent_users'][$sender] > 3) {
                  if (rand(1, 100) > 90) { // 10% chance to greet a regular
                      $greetings = [
                          "Chào người quen @$sender nhé! Chúc bạn ngày mới tốt lành.",
                          "Lại gặp @$sender rồi, dạo này đỏ không bạn ơi?",
                          "Hế lô @$sender, nãy giờ bú đậm chưa?",
                          "Ơ @$sender nè, nãy giờ tui hóng bạn mãi."
                      ];
                      return $greetings[array_rand($greetings)];
                  }
             }
        }

        // 4. Random talk (Chỉ thực hiện nếu không có phản hồi nào cho history)
        if (rand(1, 100) > 85) {
            // Check for time-based greetings first
            $hour = (int)date('H');
            $day = (int)date('N'); // 1-7
            
            if ($hour >= 5 && $hour <= 9 && isset($personality['time_morning'])) {
                if (rand(1, 100) > 70) return $personality['time_morning'][array_rand($personality['time_morning'])];
            }
            if (($hour >= 23 || $hour <= 3) && isset($personality['time_night'])) {
                if (rand(1, 100) > 70) return $personality['time_night'][array_rand($personality['time_night'])];
            }
            if (($day == 6 || $day == 7) && isset($personality['time_weekend'])) {
                if (rand(1, 100) > 70) return $personality['time_weekend'][array_rand($personality['time_weekend'])];
            }

            return $personality['greet'][array_rand($personality['greet'])];
        }

        return null;
    }
}
