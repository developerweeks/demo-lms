<?php

namespace Drupal\login_gov\Plugin\OpenIDConnectClient;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\key\Entity\Key;
use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Login.gov OpenID Connect client.
 *
 * Implements OpenID Connect Client plugin for Login.gov.
 *
 * @OpenIDConnectClient(
 *   id = "login_gov",
 *   label = @Translation("Login.Gov")
 * )
 */
class OpenIDConnectLoginGovClient extends OpenIDConnectClientBase {

  /**
   * A list of data fields available on login.gov.
   *
   * @var array
   */
  protected static $userinfoFields = [
    'all_emails' => 'All emails',
    'given_name' => 'First name',
    'family_name' => 'Last name',
    'address' => 'Address',
    'phone' => 'Phone',
    'birthdate' => 'Date of birth',
    'social_security_number' => 'Social security number',
    'verified_at' => 'Verification timestamp',
    'x509' => 'x509',
    'x509_subject' => 'x509 Subject',
    'x509_presented' => 'x509 Presented',
  ];

  /**
   * A list of fields we always request from the site.
   *
   * @var array
   */
  protected static $alwaysFetchFields = [
    'sub' => 'UUID',
    'email' => 'Email',
    'ial' => 'Identity Assurance Level',
    'aal' => 'Authenticator Assurance Level',
  ];

  /**
   * A mapping of userinfo fields to the scopes required to receive them.
   *
   * @var array
   */
  protected static $fieldToScopeMap = [
    'sub' => 'openid',
    'email' => 'email',
    'all_emails' => 'all_emails',
    'ial' => 'openid',
    'aal' => 'openid',
    'given_name' => 'profile:name',
    'family_name' => 'profile:name',
    'address' => 'address',
    'phone' => 'phone',
    'birthdate' => 'profile:birthdate',
    'social_security_number' => 'social_security_number',
    'verified_at' => 'profile:verified_at',
    'x509' => 'x509',
    'x509_subject' => 'x509:subject',
    'x509_presented' => 'x509:presented',
    'x509_issuer' => 'x509:issuer',
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'client_id' => '',
      'acr_level' => ['ial/1'],
      'require_piv' => FALSE,
      'force_reauth' => FALSE,
      'sandbox_mode' => TRUE,
      'userinfo_fields' => [],
      'verified_within' => [
        'count' => 1,
        'units' => 'y',
      ],
      'key_private_key' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['client_id'] = [
      '#title' => $this->t('Client ID'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['client_id'],
      '#required' => TRUE,
      '#description' => $this->t('The client ID is called "Issuer" in login.gov, and looks like urn:gov:gsa:openidconnect.profiles:sp:sso:<em>agency</em>:<em>application</em>'),
    ];

    $form['sandbox_mode'] = [
      '#title' => $this->t('Sandbox Mode'),
      '#type' => 'checkbox',
      '#description' => $this->t('Check here to use the identitysandbox.gov test environment.'),
      '#default_value' => $this->configuration['sandbox_mode'],
    ];

    $form['acr_level'] = [
      '#title' => $this->t('Authentication Assurance Level'),
      '#type' => 'checkboxes',
      '#options' => [
        'ial/1' => $this->t('IAL 1 - Basic'),
        'ial/2' => $this->t('IAL 2 - Verified Identity'),
        'aal/2' => $this->t('AAL 2 - Users must re-authenticate every 12 hours'),
        'aal/3' => $this->t('AAL 3 - Users must authenticate with WebAuthn or PIV/CAC'),
      ],
      '#default_value' => $this->configuration['acr_level'],
    ];

    $form['require_piv'] = [
      '#title' => $this->t('Require PIV/CAC with AAL 3'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['require_piv'],
      '#states' => [
        'visible' => [':input[name="settings[acr_level][aal/3]"]' => ['checked' => TRUE]],
      ],
    ];

    $form['verified_within'] = [
      '#title' => $this->t('Verified within'),
      '#type' => 'item',
      '#description' => $this->t('Must be no shorter than 30 days.  Set to 0 for unlimited.'),
      '#states' => [
        'invisible' => [':input[name="settings[acr_level]"]' => ['value' => 'ial/1']],
      ],
    ];

    $form['verified_within']['count'] = [
      '#type' => 'number',
      '#default_value' => $this->configuration['verified_within']['count'],
    ];
    $form['verified_within']['units'] = [
      '#type' => 'select',
      '#options' => [
        'd' => $this->t('days'),
        'w' => $this->t('weeks'),
        'm' => $this->t('months'),
        'y' => $this->t('years'),
      ],
      '#default_value' => $this->configuration['verified_within']['units'],
    ];

    $form['userinfo_fields'] = [
      '#title' => $this->t('User fields'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => static::$userinfoFields,
      '#description' => $this->t('List of fields to fetch, which will translate to the required scopes. Some fields require IAL/2 Authentication Assurance Level. See the @login_gov_documentation for more details. The Email and UUID (sub) fields are always fetched.', ['@login_gov_documentation' => Link::fromTextAndUrl($this->t('Login.gov documentation'), Url::fromUri('https://developers.login.gov/attributes/'))->toString()]),
      '#default_value' => $this->configuration['userinfo_fields'],
    ];

    $form['force_reauth'] = [
      '#title' => $this->t('Force Reauthorization'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['force_reauth'],
      '#description' => $this->t('Require the user to login again to Login.gov. <em>Requires login.gov administrator approval.</em>'),
    ];

    $form['key_private_key'] = [
      '#title' => $this->t('Key from Key'),
      '#type' => 'key_select',
      '#default_value' => $this->configuration['key_private_key'],
      '#key_filters' => ['type' => ['asymmetric_private']],
      '#description' => ' ' . $this->t('A Private key managed by the @key_module.', ['@key_module' => Link::fromTextAndUrl($this->t('Key module'), Url::fromRoute('entity.key.collection'))->toString()]),
    ];

    // Add some custom CSS.
    $form['#attached']['library'][] = 'login_gov/login-gov-config';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints(): array {
    return $this->configuration['sandbox_mode'] ? [
      'authorization' => 'https://idp.int.identitysandbox.gov/openid_connect/authorize',
      'token' => 'https://idp.int.identitysandbox.gov/api/openid_connect/token',
      'userinfo' => 'https://idp.int.identitysandbox.gov/api/openid_connect/userinfo',
      'end_session' => 'https://idp.int.identitysandbox.gov/openid_connect/logout',
      'certs' => 'https://idp.int.identitysandbox.gov/api/openid_connect/certs',
    ] :
    [
      'authorization' => 'https://secure.login.gov/openid_connect/authorize',
      'token' => 'https://secure.login.gov/api/openid_connect/token',
      'userinfo' => 'https://secure.login.gov/api/openid_connect/userinfo',
      'end_session' => 'https://secure.login.gov/openid_connect/logout',
      'certs' => 'https://secure.login.gov/api/openid_connect/certs',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestOptions(string $authorization_code, string $redirect_uri): array {
    $endpoints = $this->getEndpoints();

    // Build the client assertion.
    // See https://developers.login.gov/oidc/#token
    $client_assertion_payload = [
      'iss' => $this->configuration['client_id'],
      'sub' => $this->configuration['client_id'],
      'aud' => $endpoints['token'],
      'jti' => $this->generateNonce(),
      'exp' => time() + 300,
    ];
    // Add the client assertion to the list of options.
    $options = [
      'client_assertion' => $this->signJwtPayload($client_assertion_payload),
      'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
      'code' => $authorization_code,
      'grant_type' => 'authorization_code',
    ];
    return [
      'form_params' => $options,
      'headers' => [
        'Accept' => 'application/json',
      ],
    ];
  }

  /**
   * Sign the JWT.
   *
   * @param array $payload
   *   An array of key-value pairs.
   *
   * @return string
   *   The signed JWT.
   */
  public function signJwtPayload(array $payload): string {
    return JWT::encode($payload, $this->getPrivateKey(), 'RS256');
  }

  /**
   * Return the private key for signing the JWTs.
   *
   * @return string
   *   The private key in PEM format.
   */
  protected function getPrivateKey(): ?string {
    $key = Key::load($this->configuration['key_private_key']);
    // Return the key's KeyValue, or fall back to the old configuration if there
    // is no Key.
    return $key ? $key->getKeyValue() : $this->configuration['private_key'];
  }

  /**
   * Get login.gov's public signing key.
   *
   * @return array|null
   *   A list of public keys.
   */
  protected function getPeerPublicKeys(): ?array {
    $endpoints = $this->getEndpoints();
    $keys_json = $this->httpClient->get($endpoints['certs'])->getBody()->getContents();
    $keys = Json::decode($keys_json);
    return JWK::parseKeySet($keys);
  }

  /**
   * Generate a one-time use code word, a nonce.
   *
   * @param int $length
   *   The length of the nonce.
   *
   * @return string
   *   The nonce.
   */
  protected function generateNonce(int $length = 26): string {
    return substr(Crypt::randomBytesBase64($length), 0, $length);
  }

  /**
   * Generate the acr_values portion of the URL options.
   *
   * @return string
   *   The Authentication Context Class Reference value.
   */
  protected function generateAcrValue(): string {
    $acrs = [];

    foreach (array_filter($this->configuration['acr_level']) as $acr_level) {
      $param = ($acr_level == 'aal/3' && $this->configuration['require_piv']) ? '?hspd12=true' : '';
      $acrs[] = 'http://idmanagement.gov/ns/assurance/' . $acr_level . $param;
    }

    return implode(' ', $acrs);
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlOptions(string $scope, GeneratedUrl $redirect_uri): array {
    $options = parent::getUrlOptions($scope, $redirect_uri);

    $nonce = $this->generateNonce();
    $options['query'] += [
      'acr_values' => $this->generateAcrValue(),
      'nonce' => $nonce,
    ];
    $this->requestStack->getCurrentRequest()->getSession()->set('login_gov.nonce', $nonce);

    if ($this->configuration['acr_level'] == '2' && $this->configuration['verified_within']['count']) {
      $options['query']['verified_within'] = $this->configuration['verified_within']['count'] . $this->configuration['verified_within']['units'];
    }
    $options['query']['prompt'] = $this->configuration['force_reauth'] ? 'login' : 'select_account';

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveTokens(string $authorization_code): ?array {
    $tokens = parent::retrieveTokens($authorization_code);

    // Verify the nonce is the one we sent earlier.
    if (!empty($tokens['id_token'])) {
      $keys = $this->getPeerPublicKeys();
      $decoded_tokens = JWT::decode($tokens['id_token'], $keys);
      $session_nonce = $this->requestStack->getCurrentRequest()->getSession()->get('login_gov.nonce');
      if (!empty($session_nonce) && ($decoded_tokens->nonce !== $session_nonce)) {
        return NULL;
      }
    }

    return $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientScopes(): ?array {
    $fields = static::$alwaysFetchFields + ($this->configuration['userinfo_fields'] ?? []);
    return array_values(array_unique(array_intersect_key(static::$fieldToScopeMap, $fields)));
  }

}
