<?php
/**
 * Helper gửi email OTP/Thông báo đơn giản qua hàm mail() có sẵn của PHP.
 * Nếu hệ thống có cấu hình SMTP riêng, hãy cập nhật các hằng số bên dưới
 * hoặc thay thế hàm sendHtmlMail() bằng thư viện bạn sử dụng (PHPMailer, etc.).
 */

if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', 'no-reply@gtlmanh.id.vn');
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'Giải Trí Lành Mạnh');
}

if (!defined('MAIL_OTP_EXPIRE_MINUTES')) {
    define('MAIL_OTP_EXPIRE_MINUTES', 5);
}

if (!defined('MAIL_OTP_MAX_ATTEMPTS')) {
    define('MAIL_OTP_MAX_ATTEMPTS', 5);
}

/**
 * Gửi email HTML đơn giản.
 */
function sendHtmlMail(string $to, string $subject, string $body): bool
{
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
    ];

    return mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}

/**
 * Gửi OTP tới email người dùng.
 */
function sendOtpEmail(string $to, string $otpCode, string $contextLabel): bool
{
    $subject = sprintf('Mã OTP %s của bạn', $contextLabel);

    $body = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; padding: 16px;">
            <h2 style="color: #4f46e5; margin-bottom: 12px;">Xin chào!</h2>
            <p>Bạn (hoặc ai đó) vừa yêu cầu xác thực cho bước <strong>' . htmlspecialchars($contextLabel) . '</strong> tại <strong>' . MAIL_FROM_NAME . '</strong>.</p>
            <p>Mã OTP của bạn là:</p>
            <p style="font-size: 32px; letter-spacing: 6px; font-weight: bold; color: #1e293b; margin: 16px 0;">' . htmlspecialchars($otpCode) . '</p>
            <p>Mã chỉ có hiệu lực trong <strong>' . MAIL_OTP_EXPIRE_MINUTES . ' phút</strong>. Không chia sẻ mã này cho bất kỳ ai.</p>
            <p>Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email.</p>
            <p style="margin-top: 24px;">Trân trọng,<br>' . MAIL_FROM_NAME . '</p>
        </div>
    ';

    return sendHtmlMail($to, $subject, $body);
}

/**
 * Sinh mã OTP 6 chữ số.
 */
function generateOtpCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Ẩn bớt email khi hiển thị cho người dùng.
 */
function maskEmail(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    [$local, $domain] = explode('@', $email);
    $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 0));
    $domainParts = explode('.', $domain);
    $domainParts[0] = substr($domainParts[0], 0, 1) . str_repeat('*', max(strlen($domainParts[0]) - 1, 0));

    return $maskedLocal . '@' . implode('.', $domainParts);
}

