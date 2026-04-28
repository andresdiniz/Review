<?php
// src/Form/ProductFilterType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categoryChoices = array_combine($options['categories'], $options['categories']);

        $builder
            ->add('search', SearchType::class, [
                'required' => false,
                'label'    => false,
                'attr'     => [
                    'placeholder' => 'Buscar produto...',
                    'class'       => 'form-control',
                ],
            ])
            ->add('category', ChoiceType::class, [
                'choices'     => $categoryChoices,
                'placeholder' => 'Todas as categorias',
                'required'    => false,
                'label'       => false,
                'attr'        => ['class' => 'form-select'],
            ])
            ->add('min_price', NumberType::class, [
                'required' => false,
                'label'    => false,
                'attr'     => [
                    'placeholder' => 'Preço mín. (R$)',
                    'class'       => 'form-control',
                    'min'         => 0,
                    'step'        => '0.01',
                ],
            ])
            ->add('max_price', NumberType::class, [
                'required' => false,
                'label'    => false,
                'attr'     => [
                    'placeholder' => 'Preço máx. (R$)',
                    'class'       => 'form-control',
                    'min'         => 0,
                    'step'        => '0.01',
                ],
            ])
            ->add('order_by', ChoiceType::class, [
                'choices' => [
                    'Mais recentes'    => 'createdAt',
                    'Menor preço'      => 'currentPrice_asc',
                    'Maior preço'      => 'currentPrice_desc',
                    'Nome A-Z'         => 'name_asc',
                    'Melhor avaliados' => 'score_desc', // NOVO
                ],
                'placeholder' => 'Ordenar por',
                'required'    => false,
                'label'       => false,
                'attr'        => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'GET',
            'csrf_protection' => false,
            'categories'      => [],
        ]);

        $resolver->setAllowedTypes('categories', 'array');
    }
}
