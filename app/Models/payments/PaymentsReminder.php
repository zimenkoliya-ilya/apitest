<?php

namespace App\Models\payments;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Action;
class PaymentsReminder extends Model
{
    use HasFactory;
    private $txt_template = 1;
    private $email_template = 1;
    // $case_id not exist.
    function send5Day(){
        Action::sendEmail($case_id, $this->email_template, array());
    }

    function send3Day(){
        Action::sendText($case_id, $this->txt_template, array());
    }
}
