<?php

/**
 * @file
 * Hooks provided by the ReqRes Integration module.
 */

/**
 * Alter the decoded API response data after it is fetched.
 *
 * This hook is invoked after the API response body is JSON-decoded and before
 * the module extracts 'items' and 'total' from the data. Implementations may
 * add, remove, or modify any key in the raw decoded array.
 */
function hook_reqres_integration_api_data_alter(array &$data): void {
  // Example: Append a custom flag to every user record.
  // We can remove some items here, but it will brake the pagination.
  foreach ($data['data'] as &$user) {
    $user['custom_field'] = 'value';
  }
}
