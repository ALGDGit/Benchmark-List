<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemListPosition;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

class ItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'attr' => ['autofocus' => true, 'autocomplete' => 'off'],
                'constraints' => [new NotBlank()],
            ])
            ->add('listPosition', EnumType::class, [
                'class' => ItemListPosition::class,
                'label' => 'Position in list',
                'choice_label' => static fn (ItemListPosition $p) => $p->label(),
                'expanded' => true,
            ])
            ->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Categories',
                'multiple' => true,
                'expanded' => false,
                'by_reference' => false,
                'query_builder' => static fn (CategoryRepository $repo) => $repo->createQueryBuilder('c')
                    ->orderBy('c.name', 'ASC'),
                'attr' => [
                    'class' => 'category-select',
                ],
                'constraints' => [
                    new NotBlank(message: 'Select at least one category.'),
                    new Count(min: 1, minMessage: 'Select at least one category.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Item::class,
        ]);
    }
}
