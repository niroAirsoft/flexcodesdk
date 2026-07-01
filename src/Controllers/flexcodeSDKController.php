<?php

namespace idekite\flexcodesdk\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

use flexcodesdk;
use Illuminate\Support\Facades\Event;

class flexcodeSDKController extends Controller
{
    public function status()
    {
    	$data = flexcodesdk::getDevice();
    	return $data;
    }

    public function ac()
    {
    	return response(flexcodesdk::activationCode());
    }

    public function register($id)
    {
    	return response(flexcodesdk::registerUrl($id));
    }

    public function save(Request $request, $id)
    {
    	$result = flexcodesdk::register($id, $request->input('RegTemp'));
        return $this->dispatchAndRespond('fingerprints.register', $result);
    }

    public function verify(Request $request, $id)
    {
        return response(flexcodesdk::verificationUrl($id, $request->all()));
    }

    public function saveverify(Request $request, $id)
    {
    	$result = flexcodesdk::verify($id, $request->input('VerPas'));
        // set action for this verification, default to login
        $result['extras'] = $request->all();
        // Let's tell laravel result of our verification
        return $this->dispatchAndRespond('fingerprints.verify', $result);
    }

    protected function dispatchAndRespond($event, array $result)
    {
        $responses = Event::dispatch($event, array($result));

        foreach ((array) $responses as $response) {
            if ($response !== null && $response !== '') {
                return response($response);
            }
        }

        if (!empty($result['redirect_url'])) {
            return response($result['redirect_url']);
        }
        
        return response('', 200);
    }
}
