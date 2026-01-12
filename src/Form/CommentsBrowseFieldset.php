<?php declare(strict_types=1);

namespace Comment\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
// use Omeka\Form\Element as OmekaElement;

class CommentsBrowseFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][query]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Search pool query', // @translate
                ],
                'attributes' => [
                    'id' => 'comment-query',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][limit]',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Limit', // @translate
                    'info' => 'Maximum number of resources to display in the preview.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment-limit',
                ],
            ])
            /*
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][pagination]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Pagination', // @translate
                    'info' => 'Show pagination to browse all resources on the same page.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment-pagination',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][sort_headings]',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Sort headings', // @translate
                    'info' => 'Display sort links for the list of results.', // @translate
                    'term_as_value' => true,
                    'prepend_value_options' => [
                        'created' => 'Created', // @translate
                        'resource_class_label' => 'Resource class', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'comment-sort-headings',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties', // @translate
                ],
            ])
            */
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][components]',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Components', // @translate
                    'value_options' => [
                        [
                            'value' => 'resource-heading',
                            'label' => 'Heading', // @translate
                        ],
                        [
                            'value' => 'resource-body',
                            'label' => 'Body', // @translate
                        ],
                        [
                            'value' => 'thumbnail',
                            'label' => 'Thumbnail', // @translate
                        ],
                    ],
                ],
                'attributes' => [
                    'id' => 'comment-components',
                    'value' => [
                        'resource-heading',
                        'resource-body',
                        'thumbnail',
                    ],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][link-text]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Link text', // @translate
                    'info' => 'Text for link to full browse view, if any.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment-link-text',
                ],
            ])
        ;
    }
}
