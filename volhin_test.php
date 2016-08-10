<?php

error_reporting(E_ALL);

function validate_email($email)
{
    $valid_data = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    return $valid_data;
}

function is_email_contain_pattern($email, $pattern)
{
    if ($pattern == null) {
        $is_contain_pattern = true;
    } else {
        $is_contain_pattern = strpos($email, $pattern) !== false;
    }
    return $is_contain_pattern;
}

function send_email(&$account)
{

    $c_name = 'name';
    $c_email = 'email';
    $c_subject = 'subject';
    $c_body = 'body';
    $c_report = 'report';
    $c_send_success = 'send_success';
    $c_user_name = $account[$c_name];
    $c_user_email = $account[$c_email];
    $c_header = '<h1>Здравствуйте ' . $c_user_name . '.</h1>';

    $c_post_encoding = 'utf-8';

    include_once('classes/phpmailer/class.phpmailer.php');
    $phpmailer = new PHPMailer();

    $phpmailer->Priority = 3;
    $phpmailer->CharSet = $c_post_encoding;
    $phpmailer->ContentType = 'text/html';
    $phpmailer->Encoding = '8bit';
    $phpmailer->From = 'no-replay@premierzal.ru';
    $phpmailer->FromName = 'Система спам рассылки для взрослых';
    $phpmailer->Sender = 'no-replay@premierzal.ru';
    $phpmailer->Subject = $account[$c_subject];
    $phpmailer->Body = $c_header . $account[$c_body];
    // TODO : обновить PHPMailer и работать по smtp через любой сервер
    $phpmailer->Mailer = 'mail';
    $phpmailer->AddReplyTo('no-replay@premierzal.ru');
    $phpmailer->AddAddress($c_user_email, $c_user_name);

    $phpmailer->Send();
    $mailing_error = $phpmailer->ErrorInfo;
    $stamp = get_stamp();
    $report_header = $stamp
        . ' Письмо для пользователя '
        . $c_user_name
        . ' ( '
        . $c_user_email;
    if ($mailing_error == null) {
        $report_footer =
            ' ) отправлено успешно. ';
        $account[$c_send_success ] = 1;
    } else {
        $report_footer =
            ' ) отправлено с ошибкой : '
            . $mailing_error;
        $account[$c_send_success ] = 0;
    }
    $account[$c_report] = $report_header . $report_footer;
}

function get_stamp()
{
    $current_time = date('Y-m-d H:i:s');
    $result = '<br />' . $current_time . ' : ';
    return $result;
}

function sort_accounts_by_registration($a, $b)
{
    $c_date_registration = 'date_registration';
    $date_a = $a[$c_date_registration];
    $date_b = $b[$c_date_registration];
    $result = 0;
    if ($date_a > $date_b) {
        $result = 1;
    } elseif ($date_a < $date_b) {
        $result = -1;
    }
    return $result;
}

function send_spam($accounts, $address_pattern, $subject, $body)
{

    $stamp = get_stamp();
    $report = $stamp
        . ' Рассылка запущена с параметрами : шаблон - '
        . $address_pattern . '; тема - '
        . $subject . '; текст - '
        . $body
        . ';';

    $current_hour = intval(date('H'));
    $c_allow_hours_start = 10;
    $c_allow_hours_finish = 22;

    $allow_spam = $current_hour >= $c_allow_hours_start && $current_hour < $c_allow_hours_finish;

    $sort_accounts = false;
    if (!$allow_spam) {
        $stamp = get_stamp();
        $report .= $stamp . ' Текущее время ' . $current_hour . ' часов - рассылка запрещена.';
    } else {
        $sort_accounts = true;
    }

    $c_name = 'name';
    $c_email = 'email';
    $c_age = 'age';
    //$c_date_registration = 'date_registration';
    $c_spam_disable = 'spam_disable';
    $c_subject = 'subject';
    $c_body = 'body';
    $c_send_success = 'send_success';
    $c_report = 'report';

    $mailing_list = array();
    $let_send_spam = 0;
    if ($sort_accounts) {
        usort($accounts, "sort_accounts_by_registration");

        foreach ($accounts as $account) {
            $email = $account[$c_email];
            $name = $account[$c_name];
            $spam_disable = $account[$c_spam_disable];
            $age = $account[$c_age];
            list($report, $may_send) = may_send($address_pattern, $email, $name, $spam_disable, $age, $report);
            if ($may_send) {
                $account [$c_subject] = $subject;
                $account [$c_body] = $body;
                $account[$c_report] = null;
                $account[$c_send_success] = null;
                $mailing_list[] = $account;
            }
        }

        $let_send_spam = count($mailing_list) > 0;
    }

    $send_with_success = 0;
    if ($let_send_spam) {
        $list_count = count($mailing_list);
        for($i=0; $i<$list_count;$i++ ){

            $mailing_item = $mailing_list[$i];
            send_email($mailing_item );
            $report .= $mailing_item [$c_report];
            $send_with_success += $mailing_item [$c_send_success];
        }
    }


    $stamp = get_stamp();
    $report .= $stamp . ' Рассылка спама завершена, писем отправлено : ' . $send_with_success ;

    return $report;
}

/**
 * @param $address_pattern
 * @param $email
 * @param $name
 * @param $spam_disable
 * @param $age
 * @param $report
 * @return array
 */
function may_send($address_pattern, $email, $name, $spam_disable, $age, $report)
{
    $c_adult_limit = 18;
    $is_contain_pattern = is_email_contain_pattern($email, $address_pattern);
    if (!$is_contain_pattern) {
        $stamp = get_stamp();
        $report .= $stamp
            . ' Пользователь '
            . $name
            . ' имеет адрес '
            . $email
            . ' , шаблон не обнаружен "'
            . $address_pattern
            . '" - рассылка не будет сделана.';
    }

    $is_valid_email = validate_email($email);
    if (!$is_valid_email) {
        $stamp = get_stamp();
        $report .= $stamp
            . ' Пользователь '
            . $name
            . ' имеет адрес '
            . $email
            . ' , который не является адресом электронной почты - рассылка не возможна.';
    }

    $is_spam_disable = $spam_disable > 0;
    if ($is_spam_disable) {
        $stamp = get_stamp();
        $report .= $stamp
            . ' Пользователя '
            . $name
            . ' рассылка спама отключена  - рассылка не будет сделана.';
    }

    $is_adult = $age >= $c_adult_limit;
    if (!$is_adult) {
        $stamp = get_stamp();
        $report .= $stamp
            . ' Пользователь '
            . $name
            . ' имеет возраст ' . $age . ' и не является совершеннолетним  - рассылка не будет сделана.';
    }

    $may_send = $is_contain_pattern && $is_valid_email && !$is_spam_disable && $is_adult;
    return array($report, $may_send);
}

$users = array(
    array('name' => 'Владимир',
        'email' => 'vlad@saytum.ru',
        'age' => 14,
        'date_registration' => 1395338433,
        'spam_disable' => 0),
    array('name' => 'Яна',
        'email' => 'yura@saytum.ru',
        'age' => 23,
        'date_registration' => 1394128833,
        'spam_disable' => 0),
    array('name' => 'Василий',
        'email' => 'vlad@saytum.ru',
        'age' => 22,
        'date_registration' => 1092110433,
        'spam_disable' => 1),
    array('name' => 'Дима',
        'email' => 'dima@saytum.ru',
        'age' => 11,
        'date_registration' => 1191647013,
        'spam_disable' => 0),
    array('name' => 'Мария',
        'email' => 'yura@saytum.ru',
        'age' => 30,
        'date_registration' => 1223269413,
        'spam_disable' => 0),
    array('name' => 'Василий',
        'email' => 'vlad@saytum.ru',
        'age' => 22,
        'date_registration' => 1209877593,
        'spam_disable' => 0),
    array('name' => 'Павел',
        'email' => 'dima@saytum.ru',
        'age' => 11,
        'date_registration' => 1052046393,
        'spam_disable' => 1),
    array('name' => 'Юлия',
        'email' => 'yura@saytum.ru',
        'age' => 18,
        'date_registration' => 1367665593,
        'spam_disable' => 0),
    array('name' => 'Василий',
        'email' => 'vlad@yandex941kffrq.ru',
        'age' => 22,
        'date_registration' => 1209877593,
        'spam_disable' => 0),
    array('name' => 'Павел',
        'email' => 'dima@yandex941kffrq.ru',
        'age' => 11,
        'date_registration' => 1052046393,
        'spam_disable' => 1),
    array('name' => 'Юлия',
        'email' => 'yura@yandex941kffrq.ru',
        'age' => 18,
        'date_registration' => 1367665593,
        'spam_disable' => 0),
    array('name' => 'Яна',
        'email' => 'yura3xs31aytum.ru31',
        'age' => 23,
        'date_registration' => 1394128833,
        'spam_disable' => 0),
    array('name' => 'Василий',
        'email' => 'vladqr3saytum.ru',
        'age' => 22,
        'date_registration' => 1092110433,
        'spam_disable' => 1),
    array('name' => 'Дима',
        'email' => 'dimaqr41saytum.ru',
        'age' => 11,
        'date_registration' => 1191647013,
        'spam_disable' => 0),
);

$spam_result = send_spam($users, null, 'проверка связи', '<p>связь есть ?</p>');

$c_name = 'name';
$c_email = 'email';
$c_subject = 'subject';
$c_body = 'body';

$report_center = array();
$report_center[$c_name]='yura@saytum.ru';
$report_center[$c_email]='yura@saytum.ru';
$report_center[$c_subject]='Отчёт о рассылке спама';
$report_center[$c_body]=$spam_result;

send_email($report_center);