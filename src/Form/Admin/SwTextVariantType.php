<?php

namespace App\Form\Admin;

use App\Entity\SwTextVariant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * FormType admin pour creer/editer SwTextVariant.
 * Pourquoi: garder la validation serveur Symfony et une structure de champs reutilisable.
 */
final class SwTextVariantType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('family', TextType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'family',
                    'readonly' => true,
                ],
            ])
            ->add('readingMode', TextType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'reading_mode',
                    'readonly' => true,
                ],
            ])
            ->add('lang', TextType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'lang',
                    'maxlength' => 10,
                ],
            ])
            ->add('phaseKey', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    '0 (Nouvelle lune)' => 0,
                    '1 (Premier croissant)' => 1,
                    '2 (Premier quartier)' => 2,
                    '3 (Gibbeuse croissante)' => 3,
                    '4 (Pleine lune)' => 4,
                    '5 (Gibbeuse decroissante)' => 5,
                    '6 (Dernier quartier)' => 6,
                    '7 (Dernier croissant)' => 7,
                ],
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'phase_key',
                ],
            ])
            ->add('variantNo', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'variant_no',
                    'min' => 1,
                ],
            ])
            ->add('title', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'title',
                ],
            ])
            ->add('cardText', TextareaType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'rows' => 10,
                    'data-field' => 'card_text',
                ],
            ])
            ->add('fullText', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'admin-form__input',
                    'rows' => 14,
                    'data-field' => 'full_text',
                ],
            ])
            ->add('textVersion', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'text_version',
                    'min' => 1,
                ],
            ])
            ->add('isValidated', CheckboxType::class, [
                'required' => false,
                'attr' => [
                    'data-field' => 'is_validated',
                ],
            ])
            ->add('isUsed', CheckboxType::class, [
                'required' => false,
                'attr' => [
                    'data-field' => 'is_used',
                ],
            ])
            ->add('comment', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'comment',
                ],
            ])
            ->add('editorialNotes', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'admin-form__input',
                    'rows' => 4,
                    'data-field' => 'editorial_notes',
                ],
            ])
            ->add('source_variant_id', IntegerType::class, [
                'mapped' => false,
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'admin-form__input',
                    'data-field' => 'source_variant_id',
                    'min' => 1,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SwTextVariant::class,
            'csrf_protection' => true,
        ]);
    }
}
