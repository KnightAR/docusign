<?php namespace Tjphippen\Docusign;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use \Illuminate\Http\Request;

class DocusignController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function callback(Request $request) {
        dd(\Docusign::getAuthService(), \Illuminate\Support\Facades\Cache::store('redis')->get('DocusignAuth'));
        if ($request->has('code')) {
            \Docusign::getAuthService()->login();
            return response()->make('Successful');
        }
        return \Docusign::getAuthService()->loginOrRedirect();
    }
}
