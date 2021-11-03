<?php namespace Tjphippen\Docusign;

use GuzzleHttp\Client;

class Docusign
{
    private $config;
    private $client;
    private $baseUrl;
    /** @var DocusignJWTService $oauth */
    private $oauth;
    private $clientSettings;

    function __construct($config, $clientSettings=[])
    {
        $this->config = $config;
        $this->baseUrl = 'https://' . $config['environment']. '.docusign.net/restapi/' . $config['version'] . '/accounts/' . $config['account_id'] . '/';
        if(array_key_exists('query', $clientSettings)) unset($clientSettings['query']); //don't let some malicious user somehow override our request bodies.
        if(array_key_exists('json', $clientSettings)) unset($clientSettings['json']); //they'd be overwritten anyway, but still.
        $this->clientSettings = $clientSettings;
        $this->oauth = $this->getAuthService();
    }

    public function getClient() {
        if ($this->config['auth_method'] != 'jwt') {
            //$this->setBaseClient();
            return $this->client;
        }
        if ($this->config['auth_method'] == 'jwt' && !$this->oauth->hasValidToken()) {
            $this->oauth->login();
        }
        if ($this->oauth->hasValidToken()) {
            /** @var \DocuSign\eSign\Client\Auth\UserInfo $user */
            $user = \Arr::first($this->oauth->getAccount());
            /** @var \DocuSign\eSign\Client\Auth\Account $account */
            $account = \Arr::first($user->getAccounts());
            $this->baseUrl = $account->getBaseUri() . '/restapi/' . $this->config['version'] . '/accounts/' . $account->getAccountId() . '/';
            $this->setBaseClient();
            return $this->client;
        }
        throw new \Exception('Invalid Client');
    }

    public function setBaseClient() {
        $this->client = new Client(array_merge($this->clientSettings, ['base_uri' => $this->baseUrl, 'headers' => $this->getHeaders()]));
    }

    public function getUsers()
    {
        $request = $this->getClient()->get('users');
        $users = $this->rawJson($request);
        return $users['users'];
    }

    public function getUser($userId, $additional_info = false)
    {
        $additional_info = ($additional_info) ? 'true' : 'false';
        $request = $this->getClient()->get('users/' . $userId . '?additional_info=' . $additional_info);
        return $user = $this->rawJson($request);
    }

    public function getEnvelopes($envelopeIds)
    {
        $envelopes = array('envelopeIds' => $envelopeIds);
        $request = $this->getClient()->put('envelopes/status', ['json' => $envelopes, 'query' => ['envelope_ids' => 'request_body']]);
        return $envelopes = $this->rawJson($request);
    }

    public function getEnvelope($envelopeId)
    {
        $request = $this->getClient()->get('envelopes/' . $envelopeId);
        return $envelope = $this->rawJson($request);
    }

    public function getEnvelopePdf($envelopeId)
    {
        $request = $this->getClient()->get('envelopes/' . $envelopeId . '/documents/combined?certificate=true');
        return $request->getBody()->getContents();
    }

    public function getEnvelopeRecipients($envelopeId, $include_tabs = false)
    {
        $include_tabs = ($include_tabs) ? 'true' : 'false';
        $request = $this->getClient()->get('envelopes/' . $envelopeId . '/recipients?include_tabs=' . $include_tabs);
        return $recipients = $this->rawJson($request);
    }

    public function getEnvelopeTabs($envelopeId, $recipientId)
    {
        $request = $this->getClient()->get('envelopes/' . $envelopeId . '/recipients/' . $recipientId . '/tabs');
        return $tabs = $this->rawJson($request);
    }

    public function createEnvelope($data) {
        $request = $this->getClient()->post('envelopes/', ['json' => $data]);
        return $envelope = $this->rawJson($request);
    }

    public function updateEnvelope($envelopeId, $data)
    {
        $request = $this->getClient()->put('envelopes/' . $envelopeId, ['json' => $data]);
        return $envelope = $this->rawJson($request);
    }

    public function updateEnvelopeRecipients($envelopeId, $recipients)
    {
        $request = $this->getClient()->put('envelopes/' . $envelopeId . '/recipients/' , ['json' => $recipients]);
        return $recipients = $this->rawJson($request);
    }

    public function updateRecipientTabs($envelopeId, $recipientId, $tabs)
    {
        $request = $this->getClient()->put('envelopes/' . $envelopeId . '/recipients/' . $recipientId . '/tabs', ['json' => $tabs]);
        return $tabs = $this->rawJson($request);
    }

    public function deleteEnvelope($envelopeId) {
        $data = array('envelopeIds' => array($envelopeId));
        $request = $this->getClient()->put('folders/recyclebin', ['json' => $data]);
        return $deleted = $this->rawJson($request);
    }

    public function getTemplates($options = null)
    {
        $request = $this->getClient()->get('templates', ['query' => $options]);
        $templates = $this->rawJson($request);
        return $templates['envelopeTemplates'];
    }

    public function getTemplate($templateId)
    {
        $request = $this->getClient()->get('templates/' . $templateId);
        return $template = $this->rawJson($request);
    }

    public function getEnvelopeTemplates($envelopeId)
    {
        $request = $this->getClient()->get('envelopes/' . $envelopeId . '/templates');
        $templates = $this->rawJson($request);
        return $templates['templates'];
    }

    public function getFolders($templates = false)
    {
        $templates = ($templates) ? 'include' : 'only';
        $request = $this->getClient()->get('folders/?template=' . $templates);
        $folders = $this->rawJson($request);
        return $folders['folders'];
    }

    public function getFolderEnvelopes($folderId, $options = null)
    {
        $request = $this->getClient()->get('folders/' . $folderId, ['query' => $options]);
        $envelopes = $this->rawJson($request);
        return $envelopes;
    }

    public function getEnvelopeCustomFields($envelopeId)
    {
        $request = $this->getClient()->get('envelopes/' . $envelopeId . '/custom_fields');
        return $custom_fields = $this->rawJson($request);
    }

    public function createRecipientView($envelopeId, $data)
    {
        $request = $this->getClient()->post('envelopes/' . $envelopeId . '/views/recipient', ['json' => $data]);
        return $view = $this->rawJson($request);
    }

    public function createSenderView($envelopeId, $data)
    {
        $request = $this->getClient()->post('envelopes/' . $envelopeId . '/views/sender', ['json' => $data]);
        return $view = $this->rawJson($request);
    }

    public function updateEnvelopeDocuments($envelopeId, $data)
    {
        $request = $this->getClient()->put('envelopes/' . $envelopeId . '/documents', ['json' => $data]);
        return $view = $this->rawJson($request);
    }

    // Helper Functions
    public function rawJson($response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }

    public function getHeaders($accept = 'application/json', $contentType = 'application/json')
    {
        if ($this->config['auth_method'] == 'jwt' && $this->oauth->hasValidToken()) {
            return array_merge(array(
                'Accept' => $accept,
                'Content-Type' => $contentType
            ), $this->oauth->getHeader());
        }
        return array(
            'X-DocuSign-Authentication' => '<DocuSignCredentials><Username>' . $this->config['email'] . '</Username><Password>' . $this->config['password'] . '</Password><IntegratorKey>' . $this->config['integrator_key'] . '</IntegratorKey></DocuSignCredentials>',
            'Accept' => $accept,
            'Content-Type' => $contentType
        );
    }

    public function getAuthService() : DocusignJWTService
    {
        return new DocusignJWTService();
    }
}
