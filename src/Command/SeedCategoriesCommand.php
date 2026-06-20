<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:category:seed',
    description: 'Seed the default course categories (idempotent).',
)]
final class SeedCategoriesCommand extends AbstractCommand
{
    private const array DEFAULT_CATEGORIES = ['Wealth', 'Business', 'Brand'];

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Additional category name(s) to seed, on top of the defaults'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $names = array_values(array_unique(array_merge(
            self::DEFAULT_CATEGORIES,
            array_map('strval', $input->getOption('name'))
        )));

        $created = 0;

        foreach ($names as $name) {
            $slug = $this->slugger->slug($name)->lower()->toString();

            if (!is_null($this->categoryRepository->findOneBySlug($slug))) {
                continue;
            }

            $category = new Category();
            $category->setName($name);
            $category->setSlug($slug);
            $this->categoryRepository->save($category, true);
            $created++;
        }

        $this->getLogger()->info(sprintf('Seeded %d new categories', $created));
        $io->success(sprintf('Seeded %d new categ(ies); %d already present.', $created, count($names) - $created));

        return self::SUCCESS;
    }
}
