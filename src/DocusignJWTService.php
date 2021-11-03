<?php
namespace Tjphippen\Docusign;

use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Configuration;
use Tjphippen\Docusign\ConsentRequiredException;

/**
 * Based off the DocuSign Quickstart Example
 */
class DocusignJWTService
{
    //TODO: Set to 7+ days
    const TOKEN_REPLACEMENT_IN_SECONDS = 60 * 10; # 10 minutes
    protected static $expires_in;
    /** @var \DocuSign\eSign\Client\Auth\OAuthToken $access_token */
    protected static $access_token;
    protected static $expiresInTimestamp;
    protected static $account;
    /** @var DocuSign\eSign\Client\ApiClient $apiClient  */
    protected static $apiClient;

    /** @var OauthDocusign $outh  */
    protected static $outh;

    public function __construct()
    {
        $config = new Configuration();
        self::$apiClient = new ApiClient($config);
        self::$outh = new OauthDocusign(config('docusign.OAUTH'));
        if (\Illuminate\Support\Facades\Cache::store('redis')->has('DocusignAuth')) {
            foreach(\Illuminate\Support\Facades\Cache::store('redis')->get('DocusignAuth') as $key => $val) {
                self::${$key} = $val;
            }
            return;
        }
    }

    /**
     * Checker for the JWT token
     */
    protected function checkToken()
    {
        if (
            is_null(self::$access_token)
            || (time() + 120) > self::$expiresInTimestamp
        ) {
            $this->login();
        }
    }

    /**
     * Checker for the JWT token
     */
    public function hasValidToken()
    {
        if (
            is_null(self::$access_token)
            || (time() + 120) < self::$expiresInTimestamp
        ) {
            return false;
        }
        return true;
    }

    public function getAccount() {
        return self::$account;
    }

    public function getAccessToken() {
        return self::$access_token;
    }

    public function getHeader() {
        return ['Authorization' => 'Bearer ' . self::$access_token->getAccessToken()];
    }

    public function loginOrRedirect($redirect_url = null) {
        try {
            $this->login();
        } catch(ConsentRequiredException $e) {
            return redirect()->to($this->getAuthorizationURL($redirect_url));
        }
    }

    /**
     * DocuSign login handler
     */
    public function login()
    {
        try {
            self::$access_token = $this->configureJwtAuthorizationFlowByKey();
        } catch (\Throwable $th) {

            // we found consent_required in the response body meaning first time consent is needed
            if (strpos($th->getMessage(), "consent_required") !== false) {
                throw new ConsentRequiredException($th->getMessage());
            }
            throw $th;
        }

        self::$expiresInTimestamp = time() + self::$expires_in;

        if (is_null(self::$account)) {
            self::$account = self::$apiClient->getUserInfo(self::$access_token->getAccessToken());
        }

        \Illuminate\Support\Facades\Cache::store('redis')->forever('DocusignAuth', [
            'access_token' => self::$access_token,
            'expiresInTimestamp' => self::$expiresInTimestamp,
            'account' => self::$account,
        ]);

        if (self::$account) {
            return true;
        }
        return false;
    }

    /**
     * Get JWT auth by RSA key
     */
    private function configureJwtAuthorizationFlowByKey()
    {
        $JWT_CONFIG = config('docusign.JWT');
        self::$apiClient->getOAuth()->setOAuthBasePath($JWT_CONFIG['authorization_server']);
        $privateKey = file_get_contents($JWT_CONFIG['private_key_file'], true);

        $jwt_scope = $this->getScope();

        //self::$apiClient->refreshAccessToken();
        $response = self::$apiClient->requestJWTUserToken(
            $JWT_CONFIG['ds_client_id'],
            $JWT_CONFIG['ds_impersonated_user_id'],
            $privateKey,
            $jwt_scope,
        );

        return $response[0];    //code...
    }

    public function getAuthorizationURL($redirect_url = null) {
        $JWT_CONFIG = config('docusign.JWT');
        $jwt_scope = $this->getScope();
        return self::$outh->getAuthorizationUrl([
            'scope'         => $jwt_scope,
            'redirect_uri'  => config('app.url') . '/ds/callback',
            'client_id'     => $JWT_CONFIG['ds_client_id'],
            'state'         => self::$outh->getState(),
            'response_type' => 'code'
        ]);
    }

    private function getScope() {
        //Make sure to add the "impersonation" scope when using JWT authorization
        return self::$outh->getDefaultScopes()[0] . " impersonation";
    }

    /**
     * DocuSign login handler
     * @param $redirectUrl
     */
    function authCallback($redirectUrl): void
    {
        // Check given state against previously stored one to mitigate CSRF attack
        if (!self::$access_token) {
            exit('Invalid JWT state');
        } else {
            try {
                // We have an access token, which we may use in authenticated
                // requests against the service provider's API.

                $this->flash('You have authenticated with DocuSign.');
                $_SESSION['ds_access_token'] = self::$access_token->getAccessToken();
                $_SESSION['ds_refresh_token'] = self::$access_token->getRefreshToken();
                $_SESSION['ds_expiration'] = time() + (self::$access_token->getExpiresIn() * 60); # expiration time.

                // Using the access token, we may look up details about the
                // resource owner.
                $_SESSION['ds_user_name'] = self::$account[0]->getName();
                $_SESSION['ds_user_email'] = self::$account[0]->getEmail();

                $account_info = self::$account[0]->getAccounts();
                $base_uri_suffix = '/restapi';
                $_SESSION['ds_account_id'] = $account_info[0]->getAccountId();
                $_SESSION['ds_account_name'] = $account_info[0]->getAccountName();
                $_SESSION['ds_base_path'] = $account_info[0]->getBaseUri() . $base_uri_suffix;
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                // Failed to get the access token or user details.
                exit($e->getMessage());
            }
            if (!$redirectUrl) {
                $redirectUrl = $GLOBALS['app_url'];
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}
