<?php
/**
 * This file is part of the eZ RepositoryForms package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace EzSystems\RepositoryForms\Event;

final class RepositoryFormEvents
{
    /**
     * Base name for ContentType update processing events.
     */
    const CONTENT_TYPE_UPDATE = 'contentType.update';

    /**
     * Triggered when adding a FieldDefinition to the ContentTypeDraft.
     */
    const CONTENT_TYPE_ADD_FIELD_DEFINITION = 'contentType.update.addFieldDefinition';

    /**
     * Triggered when removing a FieldDefinition from the ContentTypeDraft.
     */
    const CONTENT_TYPE_REMOVE_FIELD_DEFINITION = 'contentType.update.removeFieldDefinition';

    /**
     * Triggered when saving the draft + publishing the ContentType.
     */
    const CONTENT_TYPE_PUBLISH = 'contentType.update.publishContentType';

    /**
     * Triggered when removing the draft (e.g. "cancel" action).
     */
    const CONTENT_TYPE_REMOVE_DRAFT = 'contentType.update.removeDraft';
}
