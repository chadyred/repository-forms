<?php
/**
 * This file is part of the eZ RepositoryForms package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace EzSystems\RepositoryForms\Tests\Form\Processor;

use eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionCreateStruct;
use eZ\Publish\Core\Repository\Values\ContentType\ContentType;
use eZ\Publish\Core\Repository\Values\ContentType\ContentTypeDraft;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use EzSystems\RepositoryForms\Data\ContentTypeData;
use EzSystems\RepositoryForms\Data\FieldDefinitionData;
use EzSystems\RepositoryForms\Event\FormActionEvent;
use EzSystems\RepositoryForms\Event\RepositoryFormEvents;
use EzSystems\RepositoryForms\Form\Processor\ContentTypeFormProcessor;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ContentTypeFormProcessorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $contentTypeService;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $router;

    /**
     * @var ContentTypeFormProcessor
     */
    private $formProcessor;

    protected function setUp()
    {
        parent::setUp();
        $this->contentTypeService = $this->getMock('\eZ\Publish\API\Repository\ContentTypeService');
        $this->router = $this->getMock('\Symfony\Component\Routing\RouterInterface');
        $this->formProcessor = new ContentTypeFormProcessor($this->contentTypeService, $this->router);
    }

    public function testSubscribedEvents()
    {
        self::assertSame([
            RepositoryFormEvents::CONTENT_TYPE_UPDATE => 'processDefaultAction',
            RepositoryFormEvents::CONTENT_TYPE_ADD_FIELD_DEFINITION => 'processAddFieldDefinition',
            RepositoryFormEvents::CONTENT_TYPE_REMOVE_FIELD_DEFINITION => 'processRemoveFieldDefinition',
            RepositoryFormEvents::CONTENT_TYPE_PUBLISH => 'processPublishContentType',
            RepositoryFormEvents::CONTENT_TYPE_REMOVE_DRAFT => 'processRemoveContentTypeDraft',
        ], ContentTypeFormProcessor::getSubscribedEvents());
    }

    public function testProcessDefaultAction()
    {
        $contentTypeDraft = new ContentTypeDraft();
        $fieldDef1 = new FieldDefinition();
        $fieldDefData1 = new FieldDefinitionData(['fieldDefinition' => $fieldDef1]);
        $fieldDef2 = new FieldDefinition();
        $fieldDefData2 = new FieldDefinitionData(['fieldDefinition' => $fieldDef2]);
        $contentTypeData = new ContentTypeData(['contentTypeDraft' => $contentTypeDraft]);
        $contentTypeData->addFieldDefinitionData($fieldDefData1);
        $contentTypeData->addFieldDefinitionData($fieldDefData2);

        $this->contentTypeService
            ->expects($this->at(0))
            ->method('updateFieldDefinition')
            ->with($contentTypeDraft, $fieldDef1, $fieldDefData1);
        $this->contentTypeService
            ->expects($this->at(1))
            ->method('updateFieldDefinition')
            ->with($contentTypeDraft, $fieldDef2, $fieldDefData2);
        $this->contentTypeService
            ->expects($this->at(2))
            ->method('updateContentTypeDraft')
            ->with($contentTypeDraft, $contentTypeData);

        $event = new FormActionEvent($this->getMock('\Symfony\Component\Form\FormInterface'), $contentTypeData, 'fooAction');
        $this->formProcessor->processDefaultAction($event);

    }

    public function testAddFieldDefinition()
    {
        $languageCode = 'fre-FR';
        $existingFieldDefinitions = [
            new FieldDefinition(),
            new FieldDefinition(),
        ];
        $contentTypeDraft = new ContentTypeDraft([
            'innerContentType' => new ContentType([
                'fieldDefinitions' => $existingFieldDefinitions
            ])
        ]);
        $fieldTypeIdentifier = 'ezstring';
        $expectedNewFieldDefIdentifier = sprintf(
            'new_%s_%d',
            $fieldTypeIdentifier,
            count($existingFieldDefinitions) + 1
        );

        $fieldTypeSelectionForm = $this->getMock('\Symfony\Component\Form\FormInterface');
        $fieldTypeSelectionForm
            ->expects($this->once())
            ->method('getData')
            ->willReturn($fieldTypeIdentifier);
        $mainForm = $this->getMock('\Symfony\Component\Form\FormInterface');
        $mainForm
            ->expects($this->once())
            ->method('get')
            ->with('fieldTypeSelection')
            ->willReturn($fieldTypeSelectionForm);

        $expectedFieldDefCreateStruct = new FieldDefinitionCreateStruct([
            'fieldTypeIdentifier' => $fieldTypeIdentifier,
            'identifier' => $expectedNewFieldDefIdentifier,
            'names' => [$languageCode => 'New FieldDefinition'],
        ]);
        $this->contentTypeService
            ->expects($this->once())
            ->method('addFieldDefinition')
            ->with($contentTypeDraft, $this->equalTo($expectedFieldDefCreateStruct));

        $event = new FormActionEvent(
            $mainForm,
            new ContentTypeData(['contentTypeDraft' => $contentTypeDraft]),
            'addFieldDefinition',
            ['languageCode' => $languageCode]
        );
        $this->formProcessor->processAddFieldDefinition($event);
    }

    public function testPublishContentType()
    {
        $contentTypeDraft = new ContentTypeDraft();
        $event = new FormActionEvent(
            $this->getMock('\Symfony\Component\Form\FormInterface'),
            new ContentTypeData(['contentTypeDraft' => $contentTypeDraft]),
            'publishContentType', ['languageCode' => 'eng-GB']
        );
        $this->contentTypeService
            ->expects($this->once())
            ->method('publishContentTypeDraft')
            ->with($contentTypeDraft);

        $this->formProcessor->processPublishContentType($event);
    }

    public function testPublishContentTypeWithRedirection()
    {
        $redirectRoute = 'foo';
        $redirectUrl = 'http://foo.com/bar';
        $contentTypeDraft = new ContentTypeDraft();
        $event = new FormActionEvent(
            $this->getMock('\Symfony\Component\Form\FormInterface'),
            new ContentTypeData(['contentTypeDraft' => $contentTypeDraft]),
            'publishContentType', ['languageCode' => 'eng-GB']
        );
        $this->contentTypeService
            ->expects($this->once())
            ->method('publishContentTypeDraft')
            ->with($contentTypeDraft);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with($redirectRoute)
            ->willReturn($redirectUrl);
        $expectedRedirectResponse = new RedirectResponse($redirectUrl);
        $formProcessor = new ContentTypeFormProcessor($this->contentTypeService, $this->router, ['redirectRouteAfterPublish' => $redirectRoute]);
        $formProcessor->processPublishContentType($event);
        self::assertTrue($event->hasResponse());
        self::assertEquals($expectedRedirectResponse, $event->getResponse());
    }

    public function testRemoveFieldDefinition()
    {
        $fieldDefinition1 = new FieldDefinition();
        $fieldDefinition2 = new FieldDefinition();
        $fieldDefinition3 = new FieldDefinition();
        $existingFieldDefinitions = [$fieldDefinition1, $fieldDefinition2, $fieldDefinition3];
        $contentTypeDraft = new ContentTypeDraft([
            'innerContentType' => new ContentType([
                'fieldDefinitions' => $existingFieldDefinitions
            ])
        ]);

        $fieldDefForm1 = $this->getMock('\Symfony\Component\Form\FormInterface');
        $fieldDefSelected1 = $this->getMock('\Symfony\Component\Form\FormInterface');
        $fieldDefForm1
            ->expects($this->once())
            ->method('get')
            ->with('selected')
            ->willReturn($fieldDefSelected1);
        $fieldDefSelected1
            ->expects($this->once())
            ->method('getData')
            ->willReturn(false);
        $fieldDefForm1
            ->expects($this->never())
            ->method('getData');

        $fieldDefForm2 = $this->getMock('\Symfony\Component\Form\FormInterface');
        $fieldDefSelected2 = $this->getMock('\Symfony\Component\Form\FormInterface');
        $fieldDefForm2
            ->expects($this->once())
            ->method('get')
            ->with('selected')
            ->willReturn($fieldDefSelected2);
        $fieldDefSelected2
            ->expects($this->once())
            ->method('getData')
            ->willReturn(true);
        $fieldDefForm2
            ->expects($this->once())
            ->method('getData')
            ->willReturn(new FieldDefinitionData(['fieldDefinition' => $fieldDefinition1]));

        $fieldDefForm3 = $this->getMock('\Symfony\Component\Form\FormInterface');
        $fieldDefSelected3 = $this->getMock('\Symfony\Component\Form\FormInterface');
        $fieldDefForm3
            ->expects($this->once())
            ->method('get')
            ->with('selected')
            ->willReturn($fieldDefSelected3);
        $fieldDefSelected3
            ->expects($this->once())
            ->method('getData')
            ->willReturn(true);
        $fieldDefForm3
            ->expects($this->once())
            ->method('getData')
            ->willReturn(new FieldDefinitionData(['fieldDefinition' => $fieldDefinition1]));

        $mainForm = $this->getMock('\Symfony\Component\Form\FormInterface');
        $mainForm
            ->expects($this->once())
            ->method('get')
            ->with('fieldDefinitionsData')
            ->willReturn([$fieldDefForm1, $fieldDefForm2, $fieldDefForm3]);

        $event = new FormActionEvent(
            $mainForm,
            new ContentTypeData(['contentTypeDraft' => $contentTypeDraft]),
            'removeFieldDefinition', ['languageCode' => 'eng-GB']
        );
        $this->formProcessor->processRemoveFieldDefinition($event);
    }

    public function testRemoveContentTypeDraft()
    {
        $contentTypeDraft = new ContentTypeDraft();
        $event = new FormActionEvent(
            $this->getMock('\Symfony\Component\Form\FormInterface'),
            new ContentTypeData(['contentTypeDraft' => $contentTypeDraft]),
            'removeDraft', ['languageCode' => 'eng-GB']
        );
        $this->contentTypeService
            ->expects($this->once())
            ->method('deleteContentType')
            ->with($contentTypeDraft);

        $this->formProcessor->processRemoveContentTypeDraft($event);
    }

    public function testRemoveContentTypeDraftWithRedirection()
    {
        $redirectRoute = 'foo';
        $redirectUrl = 'http://foo.com/bar';
        $contentTypeDraft = new ContentTypeDraft();
        $event = new FormActionEvent(
            $this->getMock('\Symfony\Component\Form\FormInterface'),
            new ContentTypeData(['contentTypeDraft' => $contentTypeDraft]),
            'removeDraft', ['languageCode' => 'eng-GB']
        );
        $this->contentTypeService
            ->expects($this->once())
            ->method('deleteContentType')
            ->with($contentTypeDraft);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with($redirectRoute)
            ->willReturn($redirectUrl);
        $expectedRedirectResponse = new RedirectResponse($redirectUrl);
        $formProcessor = new ContentTypeFormProcessor($this->contentTypeService, $this->router, ['redirectRouteAfterPublish' => $redirectRoute]);
        $formProcessor->processRemoveContentTypeDraft($event);
        self::assertTrue($event->hasResponse());
        self::assertEquals($expectedRedirectResponse, $event->getResponse());
    }
}
