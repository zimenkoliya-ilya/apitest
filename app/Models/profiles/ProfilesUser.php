<?php

namespace App\Models\profiles;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfilesUser extends Model
{
    use HasFactory;
    protected $table = "user_profile";
    protected $fillable = [
        'nickname',
        'tagline',
        'description',
        'office_phone',
        'mobile_phone',
        'fax_number',
        'picture_filename',
        'tmp_filename',
        'birthday',
        'gender',
        'website_url',
        'skype_handle',
        'pin',
        'key',
        'custom1',
        'custom2',
        'custom3',
    ];
    public function find_($id){

        $result = ProfilesUser::from('user_profile as uprofile')
            ->where('uprofile.user_id', $id)->get();

        return current($result->toArray());

    }

    public function update_($user_id, $data){

        $result = ProfilesUser::where('user_id', $user_id)->fill($data);
        $result->update();
        return $result;
    }

    public function storeTempFile($file){

        $folder = "/uploads/";
        if(!is_dir($folder)){
            mkdir($folder);
        }

        $filename = str_replace(' ', '-', preg_replace('/[^a-z0-9\.\_ ]/i', '', $file['name']));
        move_uploaded_file($file['tmp_name'], $folder.$filename);

        return $filename;
    }
}
