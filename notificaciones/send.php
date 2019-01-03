<?php
require("config.php");
// API access key from Google API's Console

$query = "-- noinspection SqlDialectInspection
SELECT * FROM users WHERE users.fcm_token != ''";
$stmt = $db->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$registration_ids = array();
foreach ($rows as $key => $row) {
//    echo $row['fcm_token'] .' key: '. $key;
    $registration_ids[] =  $row['fcm_token'];
}

if (!empty($_POST)) {
    $response = array("error" => FALSE);
    function send_gcm_notify($reg_id, $title, $message, $img_url, $tag)
    {

        define("GOOGLE_API_KEY", "AAAAt4iAWxk:APA91bH4gGKxhoWmp1dwePMR5JkHbv6uZmC-0GAtugJqJYBqPbljSncpb7Qp59lp9gM2AmQrkwErR1hOsmk4V9ChkBScu_zO_s_LIVlLFCITuXIpp1J2tPR-tfSg57SbVuDRqGUVLgL2sJJWZzJ1DZBUAWKEGodUqg");
        define("GOOGLE_FCM_URL", "https://fcm.googleapis.com/fcm/send");

        $fields = array(
            'registration_ids' => $reg_id,
            'priority' => "high",
            'notification' => array("title" => $title, "body" => $message, "tag" => $tag),
            'data' => array("message" => $message, "image" => $img_url),
        );

        $headers = array(
            GOOGLE_FCM_URL,
            'Content-Type: application/json',
            'Authorization: key=' . GOOGLE_API_KEY
        );

        echo "<br>";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GOOGLE_FCM_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Problem occurred: ' . curl_error($ch));
        }

        curl_close($ch);
        echo $result;
    }

    $reg_id = $registration_ids;
    $title = $_POST['title'];
    $msg = $_POST['msg'];
    $img_url = '';
    $tag = 'text';

    send_gcm_notify($reg_id, $title, $msg, $img_url, $tag);
}