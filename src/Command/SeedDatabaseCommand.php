<?php

namespace App\Command;

use App\Entity\HomepageSlot;
use App\Service\Activity\ActivityLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Configuration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-database',
    description: 'Creates 10 food categories with 25 items each (items may belong to several categories)',
)]
final class SeedDatabaseCommand extends Command
{
    private const CATEGORY_COUNT = 10;
    private const ITEMS_PER_CATEGORY = 25;

    /** @var list<array{0: string, 1: string}> name, slug */
    private const CATEGORIES = [
        ['Sopas', 'sopas'],
        ['Frutas', 'frutas'],
        ['Postres', 'postres'],
        ['Carnes', 'carnes'],
        ['Pescados', 'pescados'],
        ['Verduras', 'verduras'],
        ['Pasta', 'pasta'],
        ['Bebidas', 'bebidas'],
        ['Panes y bollería', 'panes-bolleria'],
        ['Quesos', 'quesos'],
    ];

    /** @var list<string> */
    private const FOOD_ITEMS = [
        'Sopa de tomate', 'Gazpacho andaluz', 'Crema de calabaza', 'Minestrone', 'Sopa de pollo con fideos',
        'Manzana Golden', 'Plátano de Canarias', 'Fresas del Huerto', 'Mango maduro', 'Uvas sin pepitas',
        'Tarta de queso', 'Brownie de chocolate', 'Flan casero', 'Tiramisú', 'Helado de vainilla',
        'Solomillo de ternera', 'Chuletas de cordero', 'Pollo al horno', 'Hamburguesa de vacuno', 'Costillas BBQ',
        'Salmón a la plancha', 'Merluza en salsa verde', 'Gambas al ajillo', 'Pulpo a la gallega', 'Atún en conserva',
        'Tomate cherry', 'Espinacas frescas', 'Brócoli al vapor', 'Patata nueva', 'Zanahoria rallada',
        'Espaguetis al pomodoro', 'Lasaña de carne', 'Raviolis de ricotta', 'Penne arrabbiata', 'Ñoquis con pesto',
        'Agua mineral', 'Zumo de naranja natural', 'Café con leche', 'Té verde', 'Cerveza sin alcohol',
        'Barra de pan rústico', 'Croissant de mantequilla', 'Pan de molde integral', 'Churros con azúcar', 'Brioche',
        'Manchego curado', 'Brie francés', 'Mozzarella fresca', 'Queso azul', 'Parmesano rallado',
        'Caldo de verduras', 'Lentejas estofadas', 'Ensalada César', 'Risotto de setas', 'Paella mixta',
        'Pera conferencia', 'Kiwi verde', 'Arándanos', 'Melón cantalupo', 'Piña tropical',
        'Natillas caseras', 'Galletas María', 'Mousse de limón', 'Churros con chocolate', 'Yogur griego',
        'Entrecot a la brasa', 'Albóndigas en salsa', 'Pechuga de pavo', 'Chorizo ibérico', 'Jamón serrano',
        'Bacalao al pil-pil', 'Mejillones al vapor', 'Sardinas en escabeche', 'Rodaballo al horno', 'Calamares a la romana',
        'Lechuga romana', 'Pimiento rojo', 'Calabacín salteado', 'Berenjena asada', 'Champiñones laminados',
        'Tallarines con mantequilla', 'Canelones de espinacas', 'Fettuccine Alfredo', 'Macarrones con queso', 'Tortellini en brodo',
        'Limonada casera', 'Batido de plátano', 'Infusión de manzanilla', 'Coca-Cola', 'Vino tinto de mesa',
        'Baguette crujiente', 'Pan de centeno', 'Magdalena de limón', 'Galleta de avena', 'Pan de ajo',
        'Queso fresco batido', 'Gouda holandés', 'Emmental', 'Queso de cabra', 'Cheddar madurado',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ActivityLogger $activityLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete existing data before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->connection;

        $config = $conn->getConfiguration();
        if ($config instanceof Configuration) {
            $config->setMiddlewares([]);
        }

        if ($input->getOption('force')) {
            $io->warning('Removing existing data...');
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            $conn->executeStatement('TRUNCATE TABLE homepage_slot');
            $conn->executeStatement('TRUNCATE TABLE item_category');
            $conn->executeStatement('TRUNCATE TABLE item');
            $conn->executeStatement('TRUNCATE TABLE category');
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $existing = (int) $conn->fetchOne('SELECT COUNT(*) FROM category');
        if ($existing > 0) {
            $io->error('Categories already exist. Use --force to regenerate.');

            return Command::FAILURE;
        }

        $poolSize = \count(self::FOOD_ITEMS);
        $io->title(sprintf('Seeding %d food categories (%d items each)', self::CATEGORY_COUNT, self::ITEMS_PER_CATEGORY));
        $this->activityLogger->log('console', 'seed_start', 'Command app:seed-database started', [
            'command' => 'app:seed-database',
            'categories' => self::CATEGORY_COUNT,
            'items_per_category' => self::ITEMS_PER_CATEGORY,
            'food_pool_size' => $poolSize,
            'force' => (bool) $input->getOption('force'),
        ]);

        $categoryIds = [];
        foreach (self::CATEGORIES as [$name, $slug]) {
            $conn->executeStatement(
                'INSERT INTO category (name, slug) VALUES (?, ?)',
                [$name, $slug]
            );
            $categoryIds[] = (int) $conn->lastInsertId();
        }

        $itemIds = [];
        foreach (self::FOOD_ITEMS as $foodName) {
            $conn->executeStatement('INSERT INTO item (name) VALUES (?)', [$foodName]);
            $itemIds[] = (int) $conn->lastInsertId();
        }

        $io->progressStart(self::CATEGORY_COUNT);
        $linkCount = 0;

        foreach ($categoryIds as $ci => $categoryId) {
            for ($j = 0; $j < self::ITEMS_PER_CATEGORY; ++$j) {
                $itemIndex = ($ci * 11 + $j * 3) % $poolSize;
                $conn->executeStatement(
                    'INSERT IGNORE INTO item_category (item_id, category_id) VALUES (?, ?)',
                    [$itemIds[$itemIndex], $categoryId]
                );
                ++$linkCount;
            }
            $io->progressAdvance();
        }

        $io->progressFinish();

        for ($slot = 1; $slot <= HomepageSlot::COUNT; ++$slot) {
            $conn->executeStatement(
                'INSERT INTO homepage_slot (slot_number, category_id) VALUES (?, ?)',
                [$slot, $categoryIds[$slot - 1]]
            );
        }

        $uniqueItems = (int) $conn->fetchOne('SELECT COUNT(*) FROM item');
        $this->activityLogger->log('console', 'seed_done', sprintf('Seed complete: %d categories, %d unique items', self::CATEGORY_COUNT, $uniqueItems), [
            'command' => 'app:seed-database',
            'categories' => self::CATEGORY_COUNT,
            'unique_items' => $uniqueItems,
            'category_links' => $linkCount,
            'homepage_slots' => range(1, HomepageSlot::COUNT),
        ]);
        $io->success(sprintf(
            'Seeded: %d categories, %d unique food items, %d category links (%d per category), %d homepage lists.',
            self::CATEGORY_COUNT,
            $uniqueItems,
            $linkCount,
            self::ITEMS_PER_CATEGORY,
            HomepageSlot::COUNT
        ));

        return Command::SUCCESS;
    }
}
