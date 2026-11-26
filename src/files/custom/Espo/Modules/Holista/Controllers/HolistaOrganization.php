<?php
declare(strict_types=1);

namespace Espo\Modules\Holista\Controllers;

use Espo\Core\Controllers\Record;

/**
 * The REST API resolves /api/v1/HolistaOrganization to a controller named after the entity, so a
 * module-provided entity needs this class the same way an Entity-Manager entity gets one generated.
 * Without it the endpoint answers 404 "Controller does not exist" even though the metadata and the
 * database table are in place.
 */
class HolistaOrganization extends Record
{}
