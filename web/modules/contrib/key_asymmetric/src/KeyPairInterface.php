<?php

namespace Drupal\key_asymmetric;

/**
 * Provides an interface defining the KeyPair service's exposed functions.
 */
interface KeyPairInterface {

  /**
   * Extracts properties from a key/certificate, if it has a known format.
   *
   * Warning: this is potentially very expensive.
   *
   * This would ideally be part of the Key entity (maybe?) - by having code call
   * OurKeyEntity::create() which would (optionally) set all the values from the
   * return value set in key_type_settings of the created entity. For that, we'd
   * need a custom entity subclass for public/private keys, which would require
   * a change to the Key module itself: implementing a custom storage handler
   * for the Key entity type, which enables using different classes for
   * different key types (making mapFromStorageRecords() dynamic in some
   * yet-unknown way).
   *
   * While implementing this storage class (instead of their custom
   * KeyRepositoryInterface) probably is generally useful / has more advantages,
   * it's too early for us to propose that.
   *
   * @param string $key_value
   *   The key (as a string, not an entity) in a format that is recognized by
   *   the phpseclib library.
   * @param string $password
   *   (Optional) password for a password-protected private key.
   *
   * @return array
   *   Array with the following keys (which are not necessarily all present):
   *   - type:        "private" / "public"
   *   - format:      format, e.g. "PKCS8"
   *   - key_size:    key size in bits
   *   - algo:        encryption algo, e.g. "RSA"
   *   - hash_algo:   hashing algo, e.g. "sha256" (not sure why lowercase)
   *   - comment:     non-empty comment
   *   - fingerprint: fingerprint for public key
   *   - has_public:  TRUE/FALSE indicating whether this private key has its
   *                  public key included
   *   - cert:
   *     - subject:    (an X.509 Distinguished Name)
   *     - issuer:     (same)
   *     - not_before: (date in unambiguous string notation, UTC)
   *     - not_after:  (same)
   *     - validity:   (would only be set if not_before/not_after are not
   *                   recognized)
   *
   * @throws \RuntimeException
   *   If the input string is not recognized as a key.
   */
  public function getKeyProperties(string $key_value, ?string $password = NULL): ?array;

}
