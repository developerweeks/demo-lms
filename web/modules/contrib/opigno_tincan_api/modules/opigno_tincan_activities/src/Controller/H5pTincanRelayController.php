<?php

namespace Drupal\opigno_tincan_activities\Controller;

use TinCan\Statement;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\opigno_tincan_api\OpignoTinCanApiStatements;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines TinCan relay controller.
 *
 * @package Drupal\opigno_tincan_activities\Controller
 */
class H5pTincanRelayController extends ControllerBase {

  /**
   * H5pTincanStatementRelay.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return object
   *   Return JsResponse object.
   */
  public function h5pTincanStatementRelay(Request $request) {

    $data = $request->request->get('statement', '');
    $data = json_decode($data, TRUE);

    // Normally $data['result']['score']['max'] should be bigger than 0.
    // Such case means that there are no validation rules set for the the
    // question (for example, no keywords set for essay). This triggers the PHP
    // exception, see TinCan\Score::setMax().
    // Currently required fields inside TinCan iframe are not validated from
    // LP manager page, though validation works fine on the create/edit activity
    // form.
    if (isset($data['result']['score']['max']) && $data['result']['score']['max'] === 0) {
      unset($data['result']['score']['max']);
    }

    // Set object id.
    $aid = $data['object']['definition']['extensions']['http://h5p.org/x-api/h5p-local-content-id'];
    $url = Url::fromRoute('entity.opigno_activity.canonical',
      ['opigno_activity' => $aid],
      ['absolute' => TRUE])
      ->toString();
    $data['object']['id'] = $url;

    // Try to create and send the statement.
    if (class_exists('TinCan\Statement')) {
      try {
        $statement = new Statement($data);
      }
      catch (Exception $e) {
        $this->getLogger('opigno_tincan')
          ->error('The following statement could not be created: <br /><pre>' . print_r($data, TRUE) . '</pre><br />This exception was raised: ' . $e->getMessage());
        return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
      }

      $statement->stamp();
      // Sending statement.
      OpignoTinCanApiStatements::sendStatement($statement);
    }

    return new JsonResponse(NULL, Response::HTTP_OK);
  }

}
