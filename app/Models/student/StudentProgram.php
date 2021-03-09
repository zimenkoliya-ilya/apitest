<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Validator;
class StudentProgram extends Model
{
    use HasFactory;
    protected $table = "student_programs";
    protected $fillable=[
        'case_id',
        'type',
        'student_plan_id',
        'months',
        'number_loans',
        'payment',
        'rate',
        'loan_status',
        'total_amount',
        'servicer_accnt',
        'fsa_setup',
        'active',
        'created',
        'created_by',
        'updated',
        'updated_by',
        'standard_payment',
        'previous_payment',
        'new_payment',
        'service_type',
        'total_included_amount',
        'total_loans',
        'multiple_servicers',

    ];
    static function findByCase($case_id){
        return current(StudentProgram::where('case_id', $case_id)->limit(1)->get()->toArray());
    }

    static function findByCaseIDs($case_ids){
        return StudentProgram::whereIn('case_id', $case_ids)->get()->toArray();
    }

    static function findByCaseAndType($case_id, $type){
        return StudentProgram::where('case_id', $case_id)
            ->where('type', $type)
            ->get()->toArray();
    }

    static function findActiveProgram($case_id){
        return StudentProgram::where('case_id', $case_id)->where('active', 1)->get()->toArray();
    }

    static function findByCompanyID($company_id){
        $result =  StudentProgram::
            leftJoin('cases', 'cases.id', 'student_programs.case_id')
            ->where('cases.company_id', $company_id)
            ->get();
        return $result->toArray();
    }

    static function saveProgram($data){
        $program = self::findByCase($data['case_id']);
        if(isset($program) && !empty($program)){

            $data['updated'] = date('Y-m-d H:i:s');
            $data['updated_by'] = $_SESSION['user']['id'];
            unset($data['created']);
            unset($data['created_by']);

            $result = StudentProgram::where('id', $program['id'])->fill($data);
            $result->update();

        }else{
            $result = StudentProgram::create($data);
        }
        return $result;
    }

    static function updateActivePlan($id, $case_id){
        $result = StudentProgram::where('id', $id)->where('case_id', $case_id)->fill(['active' => 1]);
        $result->update();
    }

    static function resetPrograms($case_id){
        StudentProgram::where('case_id', $case_id)->delete();
    }

    static function pullProgram2(){


        $url = "https://studentloans.gov/myDirectLoan/mobile/repayment/repaymentEstimator.action";
        $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';
        $machine = 'PC_A_';
        $cookies = "/var/www/code/sls.aperturesites.com/crm/crm/tmp/cookies/".$machine."_cookie.txt";

        $options = array(
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => false,     // follow redirects
            CURLOPT_ENCODING       => "",       // handle all encodings
            CURLOPT_USERAGENT      => $userAgent, // who am i
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIESESSION  => false,
            CURLOPT_COOKIEJAR      => $cookies,
            CURLOPT_COOKIEFILE     => $cookies
        );

        $ch      = curl_init( $url );
        curl_setopt_array( $ch, $options );
        $content = curl_exec( $ch );
        $err     = curl_errno( $ch );
        $errmsg  = curl_error( $ch );
        $header  = curl_getinfo( $ch );

        $header['errno']   = $err;
        $header['errmsg']  = $errmsg;
        $header['content'] = $content;

        print_r($header);


    }

    // problem phpquery class not exist
    static function pullProgram(){

        $url = "https://studentloans.gov/myDirectLoan/repaymentEstimator.action";
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.48 Safari/537.36)';
        $machine = 'PC_A';
        $cookies = "/var/www/code/sls.aperturesites.com/crm/crm/tmp/cookies/".$machine."_cookie.txt";

        $options = array(
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING       => "",       // handle all encodings
            CURLOPT_USERAGENT      => $userAgent, // who am i
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIESESSION  =>false,
            CURLOPT_COOKIEJAR      => $cookies,
            CURLOPT_COOKIEFILE     => $cookies
        );

        $ch      = curl_init( $url );
        curl_setopt_array( $ch, $options );
        $content = curl_exec( $ch );
        $err     = curl_errno( $ch );
        $errmsg  = curl_error( $ch );
        $header  = curl_getinfo( $ch );


        $header['errno']   = $err;
        $header['errmsg']  = $errmsg;
        $header['content'] = $content;

        /* POST */
        $p = array(
            'ssn'                   => '138703891',
            'firstTwoCharsLastName' => 'Ve',
            'dob.month'             => '06',
            'dob.day'               => '09',
            'dob.year'              => '1979',
            'pin'                   => '1741',
            'Sign In.x'             => '21',
            'Sign In.y'             => '12'

        );

        $options_post = array(
            CURLOPT_REFERER => 'https://studentloans.gov/myDirectLoan/index.action',
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $p,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        curl_setopt_array( $ch, $options_post );
        $postresult = curl_exec($ch);



        $info_change = array(
            'locale'                     => 'en-us',
            '_termsAndConditions'        => 'on',
            'eCorrespondenceParticipant' => 'false',
            'emailAddress'               => 'joejvelez@gmail.com',
            'confirmEmailAddress'        => 'joejvelez@gmail.com',
        );

        $options_post_1 = array(
            CURLOPT_URL => 'https://studentloans.gov/myDirectLoan/updatePreferences.action',
            CURLOPT_REFERER => 'https://studentloans.gov/myDirectLoan/updatePreferences.action',
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $info_change,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        curl_setopt_array( $ch, $options_post_1 );
        $postresult_lander = curl_exec($ch);

        curl_setopt_array( $ch, array(CURLOPT_URL => 'https://studentloans.gov/myDirectLoan/consolidation.action?execution=e5s2'));
        $redirection1 = curl_exec($ch);

        /*curl_setopt_array( $ch, array(

            CURLOPT_URL => 'https://studentloans.gov/myDirectLoan/consolidation.action',
            CURLOPT_REFERER => 'https://studentloans.gov/myDirectLoan/consolidation.action?execution=e8s1',
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => array('execution' => 'e8s1'),
            CURLOPT_POST => 1,
        ));*/

        //$redirection2 = curl_exec($ch);

        print_r($redirection1);
        exit;

        curl_close( $ch );



        $url = "https://studentloans.gov/myDirectLoan/repaymentEstimator.action";
        $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        print_r($data);
        exit;


        $header = null;
        $url = "https://studentloans.gov/myDirectLoan/mobile/repayment/repaymentEstimator.action#view-repayment-plans";
        $machine = 'PC_A';

        // Todo fix location of cookie
        $cookies = "/var/www/crm/crm/tmp/cookies/".$machine."_cookie.txt";
        // and set the cURL cookie jar and file
        //Model_Log::loans('Get Loan Start');

        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_HEADER, $header);
        //curl_setopt($ch, CURLOPT_NOBODY, $header);
        //curl_setopt($ch, CURLOPT_REFERER, 'https://www.nslds.ed.gov/nslds_SA/SaFinPrivacyAccept.do');
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($ch, CURLOPT_COOKIESESSION,false);
        //curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookies);
        //curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
        $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);


        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        /*curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1));*/
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.48 Safari/537.36');
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $htmlresult = curl_exec($ch);

        print_r($htmlresult);
        exit;

        //Model_Log::loans('Sleep 2');
        sleep(2);

        //print $htmlresult;

        $doc = phpQuery::newDocument($htmlresult);
        phpQuery::selectDocument($doc);

        /*if( pq("ul li:contains('Incorrect Pin value')")){
            echo 'Incorrect Pin Value';
            die();
        }*/

        $yin1 = pq("#yin1 option:contains('".$p['pin'][0]."')")->val();
        $yin2 = pq("#yin2 option:contains('".$p['pin'][1]."')")->val();
        $yin3 = pq("#yin3 option:contains('".$p['pin'][2]."')")->val();
        $yin4 = pq("#yin4 option:contains('".$p['pin'][3]."')")->val();


        $p['sessionId'] = pq('input[name="sessionId"])')->val();;

        //Model_Log::loans('Session ID: '.$p['sessionId']);

        $p['yin1h'] = $yin1;
        $p['yin2h'] = $yin2;
        $p['yin3h'] = $yin3;
        $p['yin4h'] = $yin4;

        unset($p['pin']);

        //Model_Log::loans('Sleep 2');
        //print $p['sessionId'];
        sleep(2);


        if ($p) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $p);
        }
        $postresult = curl_exec($ch);

        //Model_Log::loans('Passing Pin: '. substr($postresult, 0 ,500));

        //print $postresult;

        curl_setopt($ch, CURLOPT_REFERER, 'https://www.nslds.ed.gov/nslds_SA/SaFinLoginPage.do');
        curl_setopt($ch, CURLOPT_URL, 'https://www.nslds.ed.gov/nslds_SA/MyData/MyStudentData.do?language=en');
        $file =  curl_exec($ch);


    }



    static function validate($factory){
        $val = Validator::make($factory,[
            'type'=>'required'
        ],[
            'type.required'=>'Program Type'
        ]);
        return $val;
    }
}
