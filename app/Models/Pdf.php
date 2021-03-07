<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;
class Pdf extends Model
{
    use HasFactory;
    function merge_multiple($form_bundle_group, $remove=false){
        // get forms
        $new_file = 'tmp' . DIRECTORY_SEPARATOR . uniqid() . '.pdf';
        $form_direction = 'resources/pdfs';

        if(!empty($form_bundle_group)) {
            // merge and return temporary location
            if(count($form_bundle_group) == 1){
                return $form_bundle_group[0];
            }

            $cmd = 'pdftk ' . implode(' ',$form_bundle_group) . ' cat output ' . $new_file;

            exec('pdftk ' . implode(' ',$form_bundle_group) . ' cat output ' . $new_file);

            if($remove == true) {
                foreach ($form_bundle_group as $form) {
                    unlink($form);
                }
            }
            // verify new file
            if(is_file($new_file)){
                return $new_file;
            }else{
                \Fuel\Core\Log::error('Model_PDF error '.$cmd);
                throw new Exception('Could not merge bundle documents');

            }
        }

        return false;

    }

}
