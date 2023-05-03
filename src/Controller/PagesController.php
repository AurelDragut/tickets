<?php

namespace App\Controller;

use App\Entity\Frage;
use App\Entity\Haendler;
use App\Entity\Menu;
use App\Entity\Seite;
use App\Entity\Slide;
use DateTime;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use phpseclib3\Net\SFTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use function Symfony\Component\String\s;


class PagesController extends AbstractController
{

    private ManagerRegistry $registry;
    private array $menus;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
        $entityManager = $this->registry->getManager();
        $this->menus = $entityManager->getRepository(Menu::class)->findAll();
    }

    #[Route('/', name: 'app_homepage')]
    public function index(): Response
    {
        $entityManager = $this->registry->getManager();
        $haendler = $entityManager->getRepository(Haendler::class)->findAll();

        $seite = $entityManager->getRepository(Seite::class)->findOneBy(['Titel' => 'Startseite']);
        $slider = $entityManager->getRepository(Slide::class)->findAll();

        return $this->render('seite.html.twig', ['seite' => $seite, 'menu' => $this->menus, 'haendler' => $haendler, 'slider' => $slider]);
    }

    #[Route('/reklamation', name: 'reklamation')]
    public function reklamation(): Response
    {
        $entityManager = $this->registry->getManager();
        $fragen = $entityManager->getRepository(Frage::class)->findAll();
        return $this->render('reklamation.html.twig', ['fragen' => $fragen, 'menu' => $this->menus]);
    }

    #[Route('/impressum', name: 'impressum')]
    public function impressum(): Response
    {
        $entityManager = $this->registry->getManager();
        $seite = $entityManager->getRepository(Seite::class)->findOneBy(['Titel' => 'Impressum']);
        return $this->render('seite.html.twig', ['seite' => $seite, 'menu' => $this->menus]);
    }

    #[Route('/datenschutz', name: 'datenschutz')]
    public function datenschutz(): Response
    {
        $entityManager = $this->registry->getManager();
        $seite = $entityManager->getRepository(Seite::class)->findOneBy(['Titel' => 'Datenschutzerklärung']);
        return $this->render('seite.html.twig', ['seite' => $seite, 'menu' => $this->menus]);
    }

    /**
     * @throws Exception
     */
    #[Route('/ebaypreisb61100', name: 'ebayPreisB61100')]
    public function ebayPreisB61100(): Response
    {
        $NowTime = date("H:i");

        $start = strtotime($NowTime);
        $stop = strtotime("23:00");

        $diff = ($stop - $start); //Diff in seconds

        $minutes = $diff / 60;

        session_cache_expire($minutes);
        session_start();
        $now = new DateTime();
        unset($_SESSION['date']);
        if (!isset($_SESSION['date']) || $_SESSION['date'] !== $now->format('Y-m-d')) {
            $_SESSION['date'] = $now->format('Y-m-d');
            $connectionParams = [
                'dbname' => 'eazybusiness',
                'user' => 'reader',
                'password' => 'reader',
                'host' => '192.168.2.80\VJTL',
                'driver' => 'pdo_sqlsrv',
            ];
            $conn = DriverManager::getConnection($connectionParams);

            $sql = "SET NOCOUNT ON; DECLARE @ebayitems TABLE (kartikel INT, cartnr VARCHAR(255), ebayPrice DECIMAL(10,2), ebayID VARCHAR(255))
DECLARE @amazonitems TABLE (kartikel INT, cartnr VARCHAR(255), amazonPrice DECIMAL(10,2), amazonPriceVersand DECIMAL(10,2), amazonASIN VARCHAR(255))
DECLARE @shopitems TABLE (kartikel INT, cartnr VARCHAR(255), ebayPrice DECIMAL(10,2), latestEbayID VARCHAR(255), amazonPrice DECIMAL(10,2), latestAmazonASIN VARCHAR(255))

INSERT INTO @ebayitems
select tArt.kartikel, tArt.cartnr, ebay.ebay_price AS ebayPrice, ebay.ItemID AS ebayID
                from tArtikel tart
                inner Join tStueckliste tst ON tst.kstueckliste = tart.kstueckliste
                inner Join tArtikel tartstuck on tst.kartikel = tartstuck.kartikel
                inner Join tStueckliste tstuckart ON tstuckart.kartikel = tartstuck.kartikel
                inner Join tArtikel tartstuckart ON tstuckart.kstueckliste = tartstuckart.kstueckliste AND tartstuckart.cartnr LIKE CONCAT('%', tart.cartnr,'%')
                left Join (SELECT ebay_item.kartikel, (CASE WHEN ebay_ShippingServiceOptions.ShippingServiceCost > 0 THEN ebay_item.StartPrice + ebay_ShippingServiceOptions.ShippingServiceCost ELSE ebay_item.StartPrice END) AS ebay_price, ebay_item.ItemID, ebay_item.endtime
FROM ebay_item 
left JOIN ebay_ShippingServiceOptions ON ebay_ShippingServiceOptions.kitem = ebay_item.kitem AND ebay_ShippingServiceOptions.ShippingService != 'DE_Pickup'
WHERE ebay_item.ItemID != '' AND (CAST(ebay_item.endtime AS DATE) >= CAST( GETDATE() AS Date))) ebay ON ebay.kartikel = tartstuckart.kartikel AND (CAST(ebay.endtime AS DATE) >= CAST( GETDATE() AS Date))
                left Join tpreis tPreis ON tpreis.kartikel = tart.kartikel
                WHERE tartstuck.cartnr LIKE 'A-%' AND tartstuck.cartnr != 'A-BS' and tartstuckart.khersteller = tArt.khersteller
                AND tpreis.kshop = 28
                GROUP BY tArt.kartikel, tArt.cartnr, ebay.ebay_price, ebay.ItemID
                
INSERT INTO @amazonitems                
select tArt.kartikel, tArt.cartnr, amazon.fprice, CASE WHEN amazon.fprice < 315 THEN amazon.fprice + 4.90 ELSE amazon.fprice + 5.90 end AS amazonPrice, amazon.casin1 AS AmazonASIN
                from tArtikel tart
                inner Join tStueckliste tst ON tst.kstueckliste = tart.kstueckliste
                inner Join tArtikel tartstuck on tst.kartikel = tartstuck.kartikel
                inner Join tStueckliste tstuckart ON tstuckart.kartikel = tartstuck.kartikel
                inner Join tArtikel tartstuckart ON tstuckart.kstueckliste = tartstuckart.kstueckliste AND (tartstuckart.cISBN LIKE CONCAT('%', tart.cartnr,'%') OR tartstuckart.cartnr LIKE CONCAT('%', tart.cartnr,'%') OR tartstuckart.chan LIKE CONCAT('%', tart.cartnr,'%'))
                inner Join pf_amazon_angebot amazon ON tartstuckart.cartnr = amazon.csellersku AND amazon.nplattform = 51
                left Join tpreis tPreis ON tpreis.kartikel = tart.kartikel
                WHERE tartstuck.cartnr LIKE 'A-%' AND tartstuck.cartnr != 'A-BS' and tartstuckart.khersteller = tArt.khersteller
                AND tpreis.kshop = 28
                GROUP BY tArt.kartikel, tArt.cartnr, amazon.fprice, amazon.casin1                              

INSERT INTO @shopitems
SELECT ebayitems.kartikel, ebayitems.cartnr, ebayPrice, MAX(ebayID) AS latestEbayID, amazonitems.amazonPriceVersand, max(amazonitems.amazonASIN) AS latestAmazonASIN 
	FROM (SELECT cartnr, MIN(ebayPrice) AS minPrice FROM @ebayitems GROUP BY cartnr) ebayIDS
	INNER JOIN @ebayitems ebayitems ON ebayIDS.cartnr = ebayitems.cartnr AND ebayIDS.minPrice = ebayitems.ebayPrice
	left JOIN (SELECT cartnr, MIN(amazonPrice) AS minPrice FROM @amazonitems GROUP BY cartnr) amazonMinPrices ON amazonMinPrices.cartnr = ebayIDS.cartnr
	left JOIN @amazonitems amazonitems ON amazonitems.cartnr = ebayitems.cartnr AND amazonitems.amazonPrice = amazonMinPrices.minPrice
GROUP BY ebayitems.kartikel, ebayitems.cartnr, ebayPrice, amazonitems.amazonPriceVersand

UPDATE tPreisDetail 
	SET tPreisDetail.fNettoPreis = T2.shoppreis FROM (
SELECT items.cartnr, 
			tPreis.kpreis AS kpreis,
			(CASE WHEN ebayPrice > amazonPrice THEN (FLOOR(MIN(amazonPrice) * 0.95)-0.10) / 1.19 
							ELSE (FLOOR(MIN(ebayPrice) * 0.95)-0.10) / 1.19 END) AS shoppreis, 
			ebayprice, 
			latestEbayID, 
			amazonprice, 
			latestAmazonASIN
		FROM @shopitems items 
		left JOIN tArtikel ON tArtikel.cartnr = items.cartnr
		left JOIN tPreis ON tPreis.kArtikel = tArtikel.kartikel AND tPreis.kShop = 28
		WHERE items.cartnr NOT LIKE '%A8' and items.cartnr NOT LIKE '%G8' and items.cartnr NOT LIKE '%SMF8'
		GROUP BY items.cartnr, kpreis, ebayprice, 
			latestEbayID, 
			amazonprice, 
			latestAmazonASIN
) as T2 WHERE tPreisDetail.kpreis IN (SELECT kpreis FROM tPreis WHERE kshop = 28) AND tPreisDetail.kPreis = T2.kpreis";

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(1, 'B61100');
            $stmt->executeQuery();

            $selectSQL = "SET NOCOUNT ON; DECLARE @ebayitems TABLE (kartikel INT, cartnr VARCHAR(255), ebayPrice DECIMAL(10,2), ebayID VARCHAR(255))
DECLARE @amazonitems TABLE (kartikel INT, cartnr VARCHAR(255), amazonPrice DECIMAL(10,2), amazonPriceVersand DECIMAL(10,2), amazonASIN VARCHAR(255))
DECLARE @shopitems TABLE (kartikel INT, cartnr VARCHAR(255), ebayPrice DECIMAL(10,2), latestEbayID VARCHAR(255), amazonPrice DECIMAL(10,2), latestAmazonASIN VARCHAR(255))

INSERT INTO @ebayitems
select tArt.kartikel, tArt.cartnr, ebay.ebay_price AS ebayPrice, ebay.ItemID AS ebayID
                from tArtikel tart
                inner Join tStueckliste tst ON tst.kstueckliste = tart.kstueckliste
                inner Join tArtikel tartstuck on tst.kartikel = tartstuck.kartikel
                inner Join tStueckliste tstuckart ON tstuckart.kartikel = tartstuck.kartikel
                inner Join tArtikel tartstuckart ON tstuckart.kstueckliste = tartstuckart.kstueckliste AND tartstuckart.cartnr LIKE CONCAT('%', tart.cartnr,'%')
                left Join (SELECT ebay_item.kartikel, (CASE WHEN ebay_ShippingServiceOptions.ShippingServiceCost > 0 THEN ebay_item.StartPrice + ebay_ShippingServiceOptions.ShippingServiceCost ELSE ebay_item.StartPrice END) AS ebay_price, ebay_item.ItemID, ebay_item.endtime
FROM ebay_item 
left JOIN ebay_ShippingServiceOptions ON ebay_ShippingServiceOptions.kitem = ebay_item.kitem AND ebay_ShippingServiceOptions.ShippingService != 'DE_Pickup'
WHERE ebay_item.ItemID != '' AND (CAST(ebay_item.endtime AS DATE) >= CAST( GETDATE() AS Date))) ebay ON ebay.kartikel = tartstuckart.kartikel AND (CAST(ebay.endtime AS DATE) >= CAST( GETDATE() AS Date))
                left Join tpreis tPreis ON tpreis.kartikel = tart.kartikel
                WHERE tartstuck.cartnr LIKE 'A-%' AND tartstuck.cartnr != 'A-BS' and tartstuckart.khersteller = tArt.khersteller
                AND tpreis.kshop = 28
                GROUP BY tArt.kartikel, tArt.cartnr, ebay.ebay_price, ebay.ItemID
                
INSERT INTO @amazonitems                
select tArt.kartikel, tArt.cartnr, amazon.fprice, CASE WHEN amazon.fprice < 315 THEN amazon.fprice + 4.90 ELSE amazon.fprice + 5.90 end AS amazonPrice, amazon.casin1 AS AmazonASIN
                from tArtikel tart
                inner Join tStueckliste tst ON tst.kstueckliste = tart.kstueckliste
                inner Join tArtikel tartstuck on tst.kartikel = tartstuck.kartikel
                inner Join tStueckliste tstuckart ON tstuckart.kartikel = tartstuck.kartikel
                inner Join tArtikel tartstuckart ON tstuckart.kstueckliste = tartstuckart.kstueckliste AND (tartstuckart.cISBN LIKE CONCAT('%', tart.cartnr,'%') OR tartstuckart.cartnr LIKE CONCAT('%', tart.cartnr,'%') OR tartstuckart.chan LIKE CONCAT('%', tart.cartnr,'%'))
                inner Join pf_amazon_angebot amazon ON tartstuckart.cartnr = amazon.csellersku AND amazon.nplattform = 51
                left Join tpreis tPreis ON tpreis.kartikel = tart.kartikel
                WHERE tartstuck.cartnr LIKE 'A-%' AND tartstuck.cartnr != 'A-BS' and tartstuckart.khersteller = tArt.khersteller
                AND tpreis.kshop = 28
                GROUP BY tArt.kartikel, tArt.cartnr, amazon.fprice, amazon.casin1                              

INSERT INTO @shopitems
SELECT ebayitems.kartikel, ebayitems.cartnr, ebayPrice, MAX(ebayID) AS latestEbayID, amazonitems.amazonPriceVersand, max(amazonitems.amazonASIN) AS latestAmazonASIN 
	FROM (SELECT cartnr, MIN(ebayPrice) AS minPrice FROM @ebayitems GROUP BY cartnr) ebayIDS
	INNER JOIN @ebayitems ebayitems ON ebayIDS.cartnr = ebayitems.cartnr AND ebayIDS.minPrice = ebayitems.ebayPrice
	left JOIN (SELECT cartnr, MIN(amazonPrice) AS minPrice FROM @amazonitems GROUP BY cartnr) amazonMinPrices ON amazonMinPrices.cartnr = ebayIDS.cartnr
	left JOIN @amazonitems amazonitems ON amazonitems.cartnr = ebayitems.cartnr AND amazonitems.amazonPrice = amazonMinPrices.minPrice
GROUP BY ebayitems.kartikel, ebayitems.cartnr, ebayPrice, amazonitems.amazonPriceVersand

SELECT items.cartnr as cartnr, 
			tPreis.kpreis AS kpreis,
			(CASE WHEN ebayPrice > amazonPrice THEN FLOOR(MIN(amazonPrice) * 0.95)-0.10
							ELSE FLOOR(MIN(ebayPrice) * 0.95)-0.10 END) AS shop_brutto_preis, 
			ebayprice as ebayPrice, 
			latestEbayID as ebayID, 
			amazonprice as amazonPreis, 
			latestAmazonASIN as ASIN
		FROM @shopitems items 
		left JOIN tArtikel ON tArtikel.cartnr = items.cartnr
		left JOIN tPreis ON tPreis.kArtikel = tArtikel.kartikel AND tPreis.kShop = 28
		WHERE items.cartnr NOT LIKE '%A8' and items.cartnr NOT LIKE '%G8' and items.cartnr NOT LIKE '%SMF8'
		GROUP BY items.cartnr, kpreis, ebayprice, 
			latestEbayID, 
			amazonprice, 
			latestAmazonASIN";

            // $items = $conn->executeQuery($selectSQL);

            $stmt = $conn->prepare($selectSQL);
            $stmt->bindValue(1, 'B61100');
            $items = $stmt->executeQuery();

            $items = $items->fetchAllAssociative();

            //echo $qb->getSQL();
            echo '<hr />';
            echo 'sku: ', $items[0]['cartnr'], '<br />';
            echo 'shop_brutto_preis: ', (double)$items[0]['shop_brutto_preis'], '<br />';
            echo 'amazon_preis: ', (double)$items[0]['amazonPreis'], '<br />';
            echo 'min_ebay_price: ', (double)$items[0]['ebayPrice'], '<br />';

            $sftp = new \phpseclib3\Net\SFTP('dedivirt2812.your-server.de', 22);
            $login_result = $sftp->login('battwb_0', 'ftkQSivQgmvFTE86');

            if (!$login_result) {
                // PHP will already have raised an E_WARNING level message in this case
                die("can't login");
            }

            $sftp->chdir('templates/NOVAChild');
            //$files = $sftp->nlist();

            for ($i=0; $i<count($items); $i++) {
                $artikelNummer = $items[$i]['cartnr'];
                $_SESSION['preise'][$artikelNummer]['shop_brutto_preis'] = (double)$items[$i]['shop_brutto_preis'];
                $_SESSION['preise'][$artikelNummer]['amazon_preis'] = (double)$items[$i]['amazonPreis'];
                $_SESSION['preise'][$artikelNummer]['ASIN'] = $items[$i]['ASIN'];
                $_SESSION['preise'][$artikelNummer]['min_ebay_price'] = (double)$items[$i]['ebayPrice'];
                $_SESSION['preise'][$artikelNummer]['ebayID'] = $items[$i]['ebayID'];

                $amazonPreis = str_replace(',','.',$_SESSION['preise'][$artikelNummer]['amazon_preis']);

                if ($amazonPreis > $_SESSION['preise'][$artikelNummer]['min_ebay_price']) {
                    $shopPreisProzent = str_replace('.', '', $_SESSION['preise'][$artikelNummer]['shop_brutto_preis'] / ($amazonPreis / 100));
                    $ebayPreisProzent = str_replace('.', '', $_SESSION['preise'][$artikelNummer]['min_ebay_price'] / ($amazonPreis / 100));
                    $amazonPreisProzent = 99;
                    $ebayAmpelFarbe = '#508cbe';
                    $arrowColorEbay = 'white';
                    $amazonAmpelFarbe = '#8eafca';
                    $arrowColorAmazon = 'white';

                } elseif ($amazonPreis < $_SESSION['preise'][$artikelNummer]['min_ebay_price']){
                    $shopPreisProzent = str_replace('.', '', $_SESSION['preise'][$artikelNummer]['shop_brutto_preis'] / ($_SESSION['preise'][$artikelNummer]['min_ebay_price'] / 100));
                    $ebayPreisProzent = 99;
                    $amazonPreisProzent = str_replace('.', '', $amazonPreis / ($_SESSION['preise'][$artikelNummer]['min_ebay_price'] / 100));
                    $ebayAmpelFarbe = '#8eafca';
                    $arrowColorEbay = 'white';
                    $amazonAmpelFarbe = '#508cbe';
                    $arrowColorAmazon = 'white';
                } else {
                    $shopPreisProzent = str_replace('.', '', $_SESSION['preise'][$artikelNummer]['shop_brutto_preis'] / ($_SESSION['preise'][$artikelNummer]['min_ebay_price'] / 100));
                    $ebayPreisProzent = 99;
                    $amazonPreisProzent = 99;
                    $ebayAmpelFarbe = '#8eafca';
                    $arrowColorEbay = 'white';
                    $amazonAmpelFarbe = '#8eafca';
                    $arrowColorAmazon = 'white';
                }
                if ($artikelNummer == 'S60044') {
                    $content[$artikelNummer] = '<div class="container">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <table id="bar-example-17" class="charts-css bar show-labels show-primary-axis show-data-axes data-spacing-30">
                                                <tbody>
                                                <tr>
                                                    <th scope="row" class="text-right" style="padding-right:1rem; font-size: 16px;"><a href="https://www.batteriescout.de/index.php?jtl_token=bf1cf55769157d2318284efce1c110f9d90f60b10345451e4127cfc75e6842ca&qs=' . $artikelNummer . '&search=" target="_blank">BatterieScout.de</a></th>
                                                    <td style="color: #fff; background-color:#004682; padding-right:1rem; font-size: 20px; --size:0.' . $shopPreisProzent . '">
                                                        <div class="arrowSign">
                                                            <div class="line-white"></div>
                                                            <i class="arrow-white right"></i>
                                                        </div>
                                                    ' . number_format($_SESSION['preise'][$artikelNummer]['shop_brutto_preis'], 2) . '&euro;</td>
                            
                                                </tr>
                                                <tr>
                                                    <th scope="row" class="text-right" style="padding-right:1rem; font-size: 16px;"><a href="https://www.ebay.de/itm/' . $_SESSION['preise'][$artikelNummer]['ebayID'] . '" target="_blank">Ebay.de</a></th>
                                                    <td style="color: #fff; background-color:'.$ebayAmpelFarbe.'; padding-right:1rem; font-size: 20px; --size:0.' . $ebayPreisProzent . '">
                                                        <div class="arrowSign">
                                                            <div class="line-'.$arrowColorEbay.'"></div>
                                                            <i class="arrow-'.$arrowColorEbay.'"></i>
                                                        </div>
                                                    ' . $_SESSION['preise'][$artikelNummer]['min_ebay_price'] . '&euro;</td>
                                                </tr>';
                                                if ($_SESSION['preise'][$artikelNummer]['ASIN'] != "") {
                                                    $content[$artikelNummer] .= '<tr>
                                                    <th scope = "row" class="text-right" style = "padding-right:1rem; font-size: 16px;" ><a href = "https://www.amazon.de/gp/product/' . $_SESSION['preise'][$artikelNummer]['ASIN'] . '" target = "_blank" > Amazon . de</a ></th >
                                                    <td style = "color: #fff; background-color:'.$amazonAmpelFarbe.'; padding-right:1rem; font-size: 20px; --size:0.' . $amazonPreisProzent . '" >
                                                        <div class="arrowSign" >
                                                            <div class="line-'.$arrowColorAmazon.'" ></div >
                                                            <i class="arrow-'.$arrowColorAmazon.'" ></i >
                                                        </div >
                                                    ' . number_format($amazonPreis, 2, ',', ' ') . '&euro; <!--(' . $_SESSION['preise'][$artikelNummer]['amazon_preis'] . ' &euro;+4,90 &euro;)--></td >
                                                </tr>';
                                                }
                                                $content[$artikelNummer] .= '</tbody>
                                            </table>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="rabatt">';
                                    echo $amazonPreis = $_SESSION['preise'][$artikelNummer]['amazon_preis'];
                                    echo '<hr />';
                                    echo $_SESSION['preise'][$artikelNummer]['min_ebay_price'];
                                    echo '<hr />';
                                    echo $grosstePreis = max($_SESSION['preise'][$artikelNummer]['min_ebay_price'], $amazonPreis);
                                    echo '<hr />';
                                    echo $prozent = 100 - ($_SESSION['preise'][$artikelNummer]['shop_brutto_preis'] / ($grosstePreis / 100));
                                    $content[$artikelNummer] .= '<div class="inhalt-rabatt"><span class="rabatt-preis">' . number_format($prozent, 0) . '</span><span class="rabatt-prozent">%</span></div>';
                                    $content[$artikelNummer] .= '
                                </div>
                                <a href="https://www.batteriescout.de/index.php?jtl_token=bf1cf55769157d2318284efce1c110f9d90f60b10345451e4127cfc75e6842ca&qs=' . $artikelNummer . '&search=" target="_blank">
                                    <img src="/bilder/S60044.jpg" class="img-fluid img-responsive rabattBild" alt="S60044 SIGA Autobatterie 12V 100AH 850A/EN">
                                </a>    
                            </div>
                        </div>
                    </div>';
                    $sftp->put('preisVergleich.tpl', $content['S60044']);
                }

                $grosstePreis = max($_SESSION['preise'][$artikelNummer]['min_ebay_price'], $amazonPreis);
                $prozent = 100 - ($_SESSION['preise'][$artikelNummer]['shop_brutto_preis'] / ($grosstePreis / 100));

                $content[$artikelNummer] = '<div class="preisVergleich">
                                    <div class="row">
                                        <h3><span class="pvrabatt">'.number_format($prozent, 0).'%</span> ERSPARNIS</h3>
                                    </div>
                                    <table id="bar-example-17" class="charts-css column show-labels show-primary-axis show-data-axes data-spacing-20">
                                                <tbody>
                                                <tr>
                                                    <th scope="row" class="text-right" style="padding-right:1rem; font-size: 16px;">BatterieScout.de</th>
                                                    <td style="color: #fff; background-color:#004682; padding-right:1rem; font-size: 20px; --size:0.' . $shopPreisProzent . '">
                                                        ' . number_format($_SESSION['preise'][$artikelNummer]['shop_brutto_preis'], 2) . '&euro;
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row" class="text-right" style="padding-right:1rem; font-size: 16px;"><a href="https://www.ebay.de/itm/' . $_SESSION['preise'][$artikelNummer]['ebayID'] . '" target="_blank">Ebay.de</a></th>
                                                    <td style="color: #fff; background-color:'.$ebayAmpelFarbe.'; padding-right:1rem; font-size: 20px; --size:0.' . $ebayPreisProzent . '">
                                                        ' . $_SESSION['preise'][$artikelNummer]['min_ebay_price'] . '&euro;
                                                    </td>
                                                </tr>';
                if ($_SESSION['preise'][$artikelNummer]['ASIN'] != "") {
                    $content[$artikelNummer] .= '<tr>
                                                    <th scope="row" class="text-right" style="padding-right:1rem; font-size: 16px;"><a href="https://www.amazon.de/gp/product/' . $_SESSION['preise'][$artikelNummer]['ASIN'] . '" target="_blank">Amazon.de</a></th>
                                                    <td style="color: #fff; background-color:' . $amazonAmpelFarbe . '; padding-right:1rem; font-size: 20px; --size:0.' . $amazonPreisProzent . '">
                                                        <span>' . number_format($amazonPreis, 2, ',', ' ') . '&euro;</span>
                                                    </td>
                                                </tr>';
                }
                $content[$artikelNummer] .= '</tbody>
                                            </table>
                               </div>';
                $sftp->put('preisVergleich/'.$artikelNummer.'.tpl', $content[$artikelNummer]);
            }
        }

        return new Response($content['S60044']);
    }

}
