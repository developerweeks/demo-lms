<?php

namespace Drupal\key_asymmetric;

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\Common\PublicKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;

/**
 * A class to encapsulate managing the key pairs and certificates.
 */
class KeyPair implements KeyPairInterface {

  /**
   * {@inheritdoc}
   */
  public function getKeyProperties(string $key_value, ?string $password = NULL): ?array {
    if (empty($key_value)) {
      throw new InvalidKeyException('Key is empty.');
    }

    // PublicKeyLoader::load() is generic and slow: it iterates over every
    // known format (MSBLOB, OpenSSH, PKCS1, PKCS*, Putty) for every known
    // encryption algorithm (EC, RSA, DSA), trying to interpret the key in all
    // those formats repeatedly until one works. In the case of public
    // certificates, all those methods fail, after which it tries loadX509(),
    // fetches the key and throws the cert away. Let's optimize for what we
    // think is our most common use case and first try that one (OpenSSL \
    // generated keys, whose default is RSA/PKCS8).
    $key = $cert = FALSE;
    try {
      // We can only try a combination of a specific encryption method + format:
      // AsymmetricKey::loadFormat('PKCS8') doesn't work because properties are
      // not initialized.
      $key = RSA::loadFormat('PKCS8', $key_value, $password);
    }
    catch (\Exception $e) {
    }
    if (!$key) {
      // OpenSSL certs are probably less commonly stored by the Key module
      // than keys, but since all certs are way at the end of the queue and we
      // can immediately recognize their algorithm/format without having to try
      // a dozen times, let's also prioritize them. loadX509() doesn't seem to
      // ever throw exceptions but still, try/catch.
      try {
        $x509 = new X509();
        $cert = $x509->loadX509($key_value);
      }
      catch (\Exception $e) {
      }
      // The following two key-load methods fall through if they throw an
      // exception. If we have a cert, we don't need to try the generic load()
      // if getPublicKey() throws an exception.
      if ($cert) {
        $key = $x509->getPublicKey();
      }
      else {
        $key = PublicKeyLoader::load($key_value, $password);
      }
    }

    $properties = [];
    if ($key) {
      if ($key instanceof PrivateKey) {
        $properties['type'] = 'private';
      }
      elseif ($key instanceof PublicKey) {
        $properties['type'] = 'public';
      }
      if (is_callable([$key, 'getPublicKey'])) {
        try {
          $properties['has_public'] = !empty($key->getPublicKey());
        }
        catch (\Exception $e) {
          $properties['has_public'] = FALSE;
        }
      }
      if (defined(get_class($key) . '::ALGORITHM')) {
        $properties['algo'] = $key::ALGORITHM;
      }
      if (is_callable([$key, 'getHash'])) {
        $properties['hash_algo'] = $key->getHash()->getHash();
        $properties['hash_size'] = $key->getHash()->getLength();
      }
      $base_properties = [
        'key_size' => 'getLength',
        'format' => 'getLoadedFormat',
        'comment' => 'getComment',
        'fingerprint' => 'getFingerprint',
      ];
      foreach ($base_properties as $property_name => $method) {
        if (is_callable([$key, $method])) {
          try {
            // At least getComment can be FALSE. For now we assume we don't
            // have any empty values.
            $value = $key->$method();
            if ($value) {
              $properties[$property_name] = $value;
            }
          }
          catch (\Exception $e) {
          }
        }
      }

      if ($cert && ($properties['type'] ?? NULL) === 'public') {
        // Subject / Issuer are a deep array and we have helper functions to
        // make them into a string. For validity we apparently don't.
        $properties['cert']['subject'] = $x509->getSubjectDN(X509::DN_STRING);
        $properties['cert']['issuer'] = $x509->getSubjectDN(X509::DN_STRING);
        if (isset($cert['tbsCertificate']['validity'])) {
          $validity = $cert['tbsCertificate']['validity'];
          if (isset($validity['notAfter']['utcTime'])
            && is_string($validity['notAfter']['utcTime'])
            && count($validity['notAfter']) == 1
            && (isset($validity['notBefore']['utcTime'])
              ? is_string($validity['notBefore']['utcTime'])
              && count($validity['notBefore']) == 1
              && count($validity) == 2
              : count($validity) == 1)) {
            if (isset($validity['notBefore']['utcTime'])) {
              $properties['cert']['not_before'] = $validity['notBefore']['utcTime'];
            }
            $properties['cert']['not_after'] = $validity['notAfter']['utcTime'];
          }
          else {
            // Format doesn't match our assumptions: be lazy about it, without
            // missing info. Not HTML safe, and callers shouldn't assume that.
            $properties['cert']['validity'] = function_exists('json_encode')
              ? json_encode($validity) : var_export($validity, TRUE);
          }
        }
      }
    }

    return $properties;
  }

}
