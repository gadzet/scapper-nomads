<?php
class Database
{
    private $pdo;

    public function __construct($dbFile)
    {
        $this->pdo = new PDO("sqlite:$dbFile");
        $this->initialize();
    }

    private function initialize()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT UNIQUE,
                domain TEXT
            )
        ");
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function insert($table, $data)
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $sql = "INSERT OR IGNORE INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, $data);
    }

    public function getAll($table)
    {
        return $this->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    }
}

class Link
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function addLink($url)
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $this->db->insert('links', ['url' => $url, 'domain' => $domain]);
    }

    public function getLinksGrouped($baseDomain)
    {
        $allLinks = $this->db->getAll('links');
        $grouped = ['same_domain' => [], 'other_domains' => []];

        foreach ($allLinks as $link) {
            if ($link['domain'] === $baseDomain) {
                $grouped['same_domain'][] = $link;
            } else {
                $grouped['other_domains'][] = $link;
            }
        }

        return $grouped;
    }
}

class LinkScraper
{
    private $baseUrl;
    private $linkModel;

    public function __construct($baseUrl, $linkModel)
    {
        $this->baseUrl = $baseUrl;
        $this->linkModel = $linkModel;
    }

    public function scrape()
    {
        $html = $this->getHtml($this->baseUrl);

        preg_match_all('/<a\s+href=["\']([^"\']+)["\']/i', $html, $matches);
        foreach ($matches[1] as $href) {
            if (!preg_match('/^https?:\/\//', $href)) {
                $href = rtrim($this->baseUrl, '/') . '/' . ltrim($href, '/');
            }
            $this->linkModel->addLink($href);
        }
    }

    private function getHtml($url)
    {
        return file_get_contents($url);
    }
}

try {
    $db = new Database('links.db');
    $linkModel = new Link($db);
    $scraper = new LinkScraper('https://nomads.lt', $linkModel);
    $scraper->scrape();
    $groupedLinks = $linkModel->getLinksGrouped('nomads.lt');
} catch (Exception $e) {
    echo 'Klaida: ' . $e->getMessage();
    die();
}

function renderLinksSection($title, $links)
{
    ?>
    <div class="mt-8">
        <h2 class="text-xl font-semibold mb-2"><?= htmlspecialchars($title) ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($links as $link): ?>
                <a href="<?= htmlspecialchars($link['url']) ?>"
                   title="<?= htmlspecialchars($link['url']) ?>"
                   class="bg-gray-100 rounded-lg p-4 block relative group"
                   target="_blank">
                    <span class="text-blue-500 font-semibold"><?= htmlspecialchars($link['domain']) ?></span>

                    <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 -translate-y-2 bg-black text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <?= htmlspecialchars($link['url']) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>
<div>
    <h1 class="text-2xl font-bold mb-4">Nuorodų sąrašas</h1>
        <?php
            renderLinksSection('Nuorodos iš to paties domeno', $groupedLinks['same_domain']);
            renderLinksSection('Nuorodos iš kitų domenų', $groupedLinks['other_domains']);
        ?>
</div>
