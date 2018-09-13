<?php

use Solspace\Freeform\Library\Integrations\DataObjects\FieldObject;

return array(
    new FieldObject('name', 'Full Name', FieldObject::TYPE_STRING, false),
    new FieldObject('first_name', 'First  Name', FieldObject::TYPE_STRING, false),
    new FieldObject('last_name', 'Last Name', FieldObject::TYPE_STRING, false),
    new FieldObject('email', 'Email', FieldObject::TYPE_STRING, false),
    new FieldObject('phone', 'Phone', FieldObject::TYPE_STRING, false),
    new FieldObject('line1', 'Address #1', FieldObject::TYPE_STRING, false),
    new FieldObject('line2', 'Address #2', FieldObject::TYPE_STRING, false),
    new FieldObject('city', 'City', FieldObject::TYPE_STRING, false),
    new FieldObject('state', 'State', FieldObject::TYPE_STRING, false),
    new FieldObject('postal_code', 'Zip', FieldObject::TYPE_STRING, false),
    new FieldObject('country', 'Country', FieldObject::TYPE_STRING, false),
);
