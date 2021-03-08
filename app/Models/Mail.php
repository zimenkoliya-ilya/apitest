<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\system\services\SystemServicesAccess;
use App\Models\system\SystemSetting;
class Mail extends Model
{
    use HasFactory;
    // problem  addadress, addreplyto, attachement function not exist
    static function send($to, $subject, $body, $headers, $company_id, $attachment=false){

        if(!SystemServicesAccess::check(SystemServicesAccess::EMAIL_SERVICE, $company_id)){
            //throw new Exception('Email Service not allowed for '.$company_id);
            return false;
        }

        $mail = new PHPMailer();

        // Get Connection Settings for Company
        $json_settings = SystemSetting::get_('smtp');
        $settings = json_decode($json_settings['value'], true);

        if(!$settings){
            throw new Exception('No SMTP settings for company '.$company_id);
        }

        // TODO use separate accounts for data
        //$mail->SMTPDebug = true;
        $mail->SMTPAuth = true;
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = $settings['host'];  // Specify main and backup server
        $mail->Port = 587;
        $mail->Username = $settings['username'];                            // SMTP username
        $mail->Password = $settings['password'];                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable encryption, 'ssl' also accepted
        $mail->From = $headers['from'];
        $mail->FromName = $headers['name'];
        $mail->addAddress($to);               // Name is optional

        if(isset($headers['reply']) && !empty($headers['reply'])) {
            $mail->addReplyTo($headers['reply']);
        }

        if($attachment){
            $mail->addAttachment($attachment);
        }

        if(isset($headers['cc']) && !empty($headers['cc'])){
            if(is_array($headers['cc'])){
                $ccs = $headers['cc'];
            }else{
                $ccs = explode(',', $headers['cc']);
            }

            foreach($ccs as $copy){
                $mail->addCC($copy);
            }
        }

        if(isset($headers['bcc']) && !empty($headers['bcc'])){
            if(is_array($headers['bcc'])){
                $bccs = $headers['bcc'];
            }else{
                $bccs = explode(',', $headers['bcc']);
            }
            foreach($bccs as $bcopy){
                $mail->addBCC($bcopy);
            }
        }

        //$mail->addCC('cc@example.com');
        //$mail->addBCC('bcc@example.com');

        //$mail->WordWrap = 50;                                 // Set word wrap to 50 characters
        //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody  = $mail->msgHTML($body);
        //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        if(!$mail->send()) {
            //echo 'Message could not be sent.';
            Log::error('Mailer Error: ' . $mail->ErrorInfo.' User:'.Model_Account::getUserId());
            return false;

        }

       return true;

    }
}
