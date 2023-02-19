<?php

namespace Drupal\hf_toolkit_rest_api_get_spins\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;

/**
 * Provides a HF Toolkit Key Resource.
 *
 * @RestResource(
 *   id = "spins_resource",
 *   label = @Translation("User Spin Balance Resource"),
 *   uri_paths = {
 *     "canonical" = "/hf_toolkit_rest_api/spins_resource/{uid}"
 *   }
 * )
 */
class UserSpinsResource extends ResourceBase {

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get($uid = ''): ResourceResponse {
    $user_spin_balance = '';

    if (!empty($uid)) {
      $account = User::load($uid);
      $spin_balance = $account->get('field_spin_balance')->getValue();
      $user_spin_balance = $spin_balance;
      $expiration_date = $account->get('field_key_expiration')->getValue();
    }

    if (!empty($user_spin_balance)) {
      $response = [
        'Spin Balance' => $user_spin_balance,
        'Expiration' => $expiration_date ?? 'no key redeemed',
      ];
    }
    else {
      $response = [
        'Spin Balance' => '0',
        'Expiration' => 'not set',
      ];
    }
    return new ResourceResponse($response);
  }

}
