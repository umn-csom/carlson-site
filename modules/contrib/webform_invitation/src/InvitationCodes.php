<?php

namespace Drupal\webform_invitation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Service that generates invitation codes and inserts them in the DB.
 */
class InvitationCodes {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new InvitationCodes instance.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Connection $connection, TimeInterface $time) {
    $this->connection = $connection;
    $this->time = $time;
  }

  /**
   * Generates a set of invitation codes for a given webform.
   *
   * @param int $webform_id
   *   The form ID for which the invitation codes are generated.
   * @param int $number
   *   The amount of codes to generate.
   * @param string $type
   *   The type of codes to generate. Either 'md5' or 'custom'.
   * @param int $length
   *   (Optional). The length in chars of each generated code.
   * @param string $set
   *   (Optional). A set of chars to use to generate the code.
   *
   * @return array
   *   An array with the number of generated codes, and error if was not
   *   possible to generate all the requested codes.
   */
  public function generate($webform_id, $number, $type = 'md5', $length = 32, $set = '') {
    $i = $l = 1;
    // Process all requested tokens.
    while ($i <= $number && $l < $number * 10) {

      $code = '';
      // Code generation.
      switch ($type) {
        case 'md5':
          $code = md5(microtime(1) * rand());
          break;

        case 'custom':
          if (empty($length) || $length < 5 || $length > 64) {
            throw new \Exception('When generating codes of custom type, length must be a number between 5 and 64');
          }
          if (empty($set)) {
            throw new \Exception('A set of different chars must be provided to generate a code of type: custom');
          }
          for ($j = 1; $j <= $length; $j++) {
            $code .= $set[rand(0, strlen($set) - 1)];
          }
          break;

        default:
          throw new \Exception("Unsuported code type. Only 'md5' and 'custom' are supported");
      }

      try {
        // Insert code to DB.
        $this->connection->insert('webform_invitation_codes')->fields([
          'webform' => $webform_id,
          'code' => $code,
          'created' => $this->time->getRequestTime(),
        ])->execute();
        $i++;
      }
      catch (\Exception $e) {
        // The generated code is already in DB, make another one.
      }
      $l++;
    }

    return [
      'error' => $i - 1 >= $number * 10,
      'count' => $i - 1,
    ];
  }

}
