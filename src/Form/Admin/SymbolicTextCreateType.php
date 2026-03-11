<?php

namespace App\Form\Admin;

use App\DTO\Admin\SymbolicTextCreateInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire serveur du modal create Symbolic Text.
 * Pourquoi: definir les champs et contraintes metier en PHP plutot qu en JS/Twig.
 * Infos: les attributs data-* restent exposes pour pre-remplir les valeurs depuis la timeline.
 */
final class SymbolicTextCreateType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<int, array<string, string>> $rowDefinitions */
        $rowDefinitions = $options['row_definitions'];
        $rowChoices = [];
        foreach ($rowDefinitions as $row) {
            if (!isset($row['label'], $row['code'])) {
                continue;
            }
            $rowChoices[(string) $row['label']] = (string) $row['code'];
        }

        $builder
            ->add('row_code', ChoiceType::class, [
                'label' => 'Ligne',
                'choices' => $rowChoices,
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__select',
                    'data-create-display-code' => '',
                    'data-create-row-code' => '',
                ],
            ])
            ->add('display_lang', TextType::class, [
                'label' => 'Lang',
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'placeholder' => 'fr',
                ],
            ])
            ->add('display_is_active', CheckboxType::class, [
                'label' => 'is_active',
                'required' => false,
            ])
            ->add('display_comment', TextType::class, [
                'label' => 'Comment',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'admin-form__input',
                    'placeholder' => 'Commentaire SW Display',
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'Texte',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-create-label' => '',
                    'placeholder' => 'Texte symbolique',
                ],
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Sous-texte',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                ],
            ])
            ->add('color', TextType::class, [
                'label' => 'Couleur',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                    'placeholder' => '#315A7B',
                ],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icone',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                ],
            ])
            ->add('content_is_current', CheckboxType::class, [
                'label' => 'is_current',
                'required' => false,
            ])
            ->add('content_is_validated', CheckboxType::class, [
                'label' => 'is_validated',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'draft' => 'draft',
                    'review' => 'review',
                    'validated' => 'validated',
                    'archived' => 'archived',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__select',
                ],
            ])
            ->add('schema_version', TextType::class, [
                'label' => 'Schema version',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                    'placeholder' => '1.0',
                ],
            ])
            ->add('editorial_notes', TextareaType::class, [
                'label' => 'Editorial notes',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                    'rows' => 3,
                ],
            ])
            ->add('content_json', TextareaType::class, [
                'label' => 'Content JSON (IA / humain)',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                    'rows' => 4,
                    'placeholder' => '{"label":"...","is_validated":false}',
                ],
            ])
            ->add('starts_at_utc', TextType::class, [
                'label' => 'Start at UTC',
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-create-start' => '',
                    'placeholder' => 'YYYY-MM-DD HH:MM[:SS]',
                ],
            ])
            ->add('ends_at_utc', TextType::class, [
                'label' => 'End at UTC',
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-create-end' => '',
                    'placeholder' => 'YYYY-MM-DD HH:MM[:SS]',
                ],
            ])
            ->add('schedule_is_published', CheckboxType::class, [
                'label' => 'is_published',
                'required' => false,
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'Priority',
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                ],
            ])
            ->add('schedule_comment', TextType::class, [
                'label' => 'Schedule comment',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                    'placeholder' => 'Commentaire SW Schedule',
                ],
            ])
            ->add('payload_json', TextareaType::class, [
                'label' => 'Payload JSON',
                'required' => false,
                'attr' => [
                    'class' => 'admin-form__input',
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SymbolicTextCreateInput::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'create_symbolic_text_form',
            'row_definitions' => [],
        ]);
        $resolver->setAllowedTypes('row_definitions', 'array');
    }
}

