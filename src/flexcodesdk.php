<?php

namespace idekite\flexcodesdk;

use Config;
use Illuminate\Support\Facades\DB;

class flexcodesdk
{
    public static function getDevice()
    {
        $data['device_name']        =  env('FLEXCODE_DEVICE');
        $data['serial_number']      =  env('FLEXCODE_SN');
        $data['verification_code']  =  env('FLEXCODE_VC');
        $data['activation_code']    =  env('FLEXCODE_AC');
        $data['verification_key']   =  env('FLEXCODE_VKEY');

        return response()->json($data);
    }

    public function registerUrl($user_id)
    {
        return  $user_id . ';SecurityKey;15;'.url('fingerprints/register/' . $user_id).';' . url('fingerprints/ac');
    }

    public function activationCode()
    {
        $device = $this->getDefaultDevice();

        if (!$device) {
            return env('FLEXCODE_AC') . env('FLEXCODE_SN');
        }

        return $device['ac'] . $device['sn'];
    }

    public function getDeviceBySn($sn)
    {
        $devices = Config::get('flexcodesdk.devices', []);
        foreach ($devices as $device) {
            if($device['sn'] === $sn){
                return $device;
            }
        }
        return false;
    }

    public function getDefaultDevice()
    {
        $devices = Config::get('flexcodesdk.devices', []);

        return $devices[0] ?? false;
    }

    public function decodeRegistrationData($serialized_data)
    {   
        @list($vStamp, $sn, $user_id, $regTemp) = explode(";", $serialized_data);
        if( !isset($vStamp) || !isset($sn) || !isset($user_id) || !isset($regTemp)){
            return array();
        }
        
        return array(
            'vStamp'     =>  $vStamp,
            'sn'         =>  $sn,
            'user_id'    =>  $user_id,
            'regTemp'    =>  $regTemp,
        );
        
    }

    public function isValidRegistration($user, $data)
    {
        
        if(!empty($user->fingerprints) || $user->id !== $data['user_id']){
            return false;
        }
        
        $device = $this->getDeviceBySn($data['sn']);
        
        $salt = md5($device['ac'].$device['vkey'].$data['regTemp'].$data['sn'].$data['user_id']);
        return (strtoupper($data['vStamp']) == strtoupper($salt)) ? true : false;
    }

    public function register($id, $serialized_data)
    {
        $data = $this->decodeRegistrationData($serialized_data);

        if (empty($data['regTemp'])) {
            return [
                'registered' => false,
                'user_id' => $id,
                'message' => 'Error decoding fingerprint data',
            ];
        }

        if ((string) $id !== (string) $data['user_id']) {
            return [
                'registered' => false,
                'user_id' => $id,
                'message' => 'User mismatch',
            ];
        }

        $device = $this->getDeviceBySn($data['sn']);
        if (!$device) {
            return [
                'registered' => false,
                'user_id' => $id,
                'message' => 'Device not configured',
            ];
        }

        $salt = md5($device['ac'] . $device['vkey'] . $data['regTemp'] . $data['sn'] . $data['user_id']);
        if (strtoupper($data['vStamp']) !== strtoupper($salt)) {
            return [
                'registered' => false,
                'user_id' => $id,
                'message' => 'Invalid registration data',
            ];
        }

        $fingerprint = $this->storeFingerprint($id, $data['regTemp']);

        return [
            'registered' => true,
            'user_id' => $id,
            'fingerprint' => $fingerprint,
            'message' => 'Fingerprints successfully registered',
            'redirect_url' => $this->resolveRedirectUrl(Config::get('flexcodesdk.redirect_after_register', '/')),
        ];
    }

    public function verificationUrl($user, $extra = array())
    {
        $query_string = http_build_query($extra);
        $userId = is_object($user) ? $user->id : $user;
        $fingerprint = $this->findFingerprint($userId);
        $fingerData = $fingerprint ? $fingerprint->{$this->fingerprintStorageConfig()['data_column']} : '';

        return $userId . ";". $fingerData .";SecurityKey;". '15' .";". url('fingerprints/verify/' . $userId . '?' . $query_string) .";". url('fingerprints/ac');
    }

    public function verify($id, $serialized_data)
    {
        $verified = false;
        $message = '';
        $fingerprint = $this->findFingerprint($id);

        if (!$fingerprint) {
            $message = 'Fingerprints unregistered';
            $result = array(
                'verified' => $verified,
                'user_id' => $id,
                'message' => $message,
            );
            return $result;
        }

        @list($user_id, $vStamp, $time, $sn) = explode(";", $serialized_data);
        if( !isset($user_id) || !isset($vStamp) || !isset($time) || !isset($sn)){
            $message = 'Incorrect fingerprint data';
            $result = array(
                'verified' => $verified,
                'user_id' => $id,
                'message' => $message,
            );
            return $result;
        }
        
        if((string) $id !== (string) $user_id){
            $message =  'User mismatch';
            $result = array(
                'verified' => $verified,
                'user_id' => $id,
                'message' => $message,
            );
            return $result;
        }
        
        $fingerData = $fingerprint->{$this->fingerprintStorageConfig()['data_column']};
        $device     = $this->getDeviceBySn($sn);

        if (!$device) {
            $result = array(
                'verified' => $verified,
                'user_id' => $id,
                'message' => 'Device not configured',
            );
            return $result;
        }
            
        $salt = md5($sn.$fingerData.$device['vc'].$time.$user_id.$device['vkey']);
        
        if(strtoupper($vStamp) == strtoupper($salt)){
            $this->markFingerprintVerified($id);

            $result = array(
                'verified' => true,
                'user_id' => $id,
                'message' => 'Verication success',
                'redirect_url' => $this->resolveRedirectUrl(Config::get('flexcodesdk.redirect_after_verify', '/')),
            );
            return $result;
        }
        $result = array(
            'verified' => false,
            'user_id' => $id,
            'message' => 'Fingerprint mismatch',
        );
        return $result;
    }

    public function getRegistrationLink($id)
    {
        return 'finspot:FingerspotReg;' . base64_encode(url('fingerprints/register/' . $id));
    }

    protected function fingerprintStorageConfig()
    {
        return Config::get('flexcodesdk.fingerprint_storage', [
            'table' => 'demo_finger',
            'user_column' => 'user_id',
            'data_column' => 'finger_data',
            'verify_column' => 'verify',
            'deleted_at_column' => 'deleted_at',
        ]);
    }

    protected function resolveRedirectUrl($pathOrUrl)
    {
        $pathOrUrl = $pathOrUrl ?: '/';
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }
        return url($pathOrUrl);
    }

    protected function findFingerprint($userId)
    {
        $config = $this->fingerprintStorageConfig();
        $query = DB::table($config['table'])
            ->where($config['user_column'], $userId);

        if (!empty($config['deleted_at_column'])) {
            $query->whereNull($config['deleted_at_column']);
        }

        return $query->orderByDesc('id')->first();
    }

    protected function storeFingerprint($userId, $fingerData)
    {
        $config = $this->fingerprintStorageConfig();
        $now = now();

        if (!empty($config['deleted_at_column'])) {
            DB::table($config['table'])
                ->where($config['user_column'], $userId)
                ->whereNull($config['deleted_at_column'])
                ->update([
                    $config['deleted_at_column'] => $now,
                    'updated_at' => $now,
                ]);
        }

        $insert = [
            $config['user_column'] => $userId,
            $config['data_column'] => $fingerData,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (!empty($config['verify_column'])) {
            $insert[$config['verify_column']] = false;
        }

        $id = DB::table($config['table'])->insertGetId($insert);

        return DB::table($config['table'])->where('id', $id)->first();
    }

    protected function markFingerprintVerified($userId)
    {
        $config = $this->fingerprintStorageConfig();

        if (empty($config['verify_column'])) {
            return;
        }

        $query = DB::table($config['table'])
            ->where($config['user_column'], $userId);

        if (!empty($config['deleted_at_column'])) {
            $query->whereNull($config['deleted_at_column']);
        }

        $query->update([
            $config['verify_column'] => true,
            'updated_at' => now(),
        ]);
    }

}
