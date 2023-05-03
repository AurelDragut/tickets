<?php

namespace App\Controller\Admin;

use App\Entity\AnfrageAngebote;
use App\Entity\Aufkleber;
use App\Entity\AuslandsVerkaeufe;
use App\Entity\BasisArtikel;
use App\Entity\Benutzer;
use App\Entity\Artikel;
use App\Entity\Auftrag;
use App\Entity\BWare;
use App\Entity\EinkaufzuVerkaufPreis;
use App\Entity\EmailVorlag;
use App\Entity\Frage;
use App\Entity\Grund;
use App\Entity\Haendler;
use App\Entity\Kommentar;
use App\Entity\MargenCheck;
use App\Entity\Meldung;
use App\Entity\Menu;
use App\Entity\MenuePunkt;
use App\Entity\NeueArtikelPerformance;
use App\Entity\Neusendung;
use App\Entity\PotenzialBasisArtikel;
use App\Entity\RausgeflogeneArtikel;
use App\Entity\Rechnung;
use App\Entity\Seite;
use App\Entity\Slide;
use App\Entity\Status;
use App\Entity\TopBasisArtikelNullBestand;
use App\Entity\VerkaufsArtikelPerformance;
use App\Entity\WarenEingang;
use App\Repository\ArtikelRepository;
use App\Repository\AuftragRepository;
use App\Repository\BenutzerRepository;
use App\Repository\GrundRepository;
use App\Repository\RechnungRepository;
use App\Repository\StatusRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;
use function Symfony\Component\Translation\t;

class DashboardController extends AbstractDashboardController
{
    private AuftragRepository $auftragRepository;
    private ArtikelRepository $artikelRepository;
    private RechnungRepository $rechnungRepository;
    private AdminUrlGenerator $adminUrlGenerator;
    private AdminContextProvider $adminContextProvider;
    private GrundRepository $grundRepository;
    private StatusRepository $statusRepository;
    private BenutzerRepository $benutzerRepository;
    private Security $security;

    public function __construct(AuftragRepository $auftragRepository,
                                ArtikelRepository $artikelRepository,
                                RechnungRepository $rechnungRepository,
                                GrundRepository $grundRepository,
                                StatusRepository $statusRepository,
                                BenutzerRepository $benutzerRepository,
                                AdminUrlGenerator $adminUrlGenerator,
                                AdminContextProvider $adminContextProvider,
                                Security $security
    )
    {
        $this->auftragRepository = $auftragRepository;
        $this->artikelRepository = $artikelRepository;
        $this->rechnungRepository = $rechnungRepository;
        $this->grundRepository = $grundRepository;
        $this->statusRepository = $statusRepository;
        $this->benutzerRepository = $benutzerRepository;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->adminContextProvider = $adminContextProvider;
        $this->security = $security;
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $letzteAuftraege = [];
        $letzteArtikel = [];
        $letzteRechnungen = [];
        $mitarbeiterListe = [];
        $grundeListe = [];
        $statusListe = [];
        $anzahl = [];

        if ($this->isGranted('ROLE_MITARBEITER')) {
            $letzteAuftraege = $this->auftragRepository->findLatest();
            $anzahl['Auftraege'] = $this->auftragRepository->count(['isArchiviert' => false]);
            $anzahl['ArchivierteAuftraege'] = $this->auftragRepository->count(['isArchiviert' => true]);
            $letzteArtikel = $this->artikelRepository->findLatest();
            $anzahl['Artikel'] = $this->artikelRepository->count([]);
            $letzteRechnungen = $this->rechnungRepository->findLatest();
            $anzahl['Rechnungen'] = $this->rechnungRepository->count([]);
            $mitarbeiterListe = $this->benutzerRepository->findMitarbeiter();
            $grundeListe = $this->grundRepository->findAll();
            $statusListe = $this->statusRepository->findAll();
        }
        elseif ($this->isGranted('ROLE_USER')) {
            $rechnungen = $this->rechnungRepository->findBy(['KdNr' => $this->adminContextProvider->getContext()->getUser()->getKdNr()]);
            $AuftragCriteria = [];
            foreach ($rechnungen as $rechnung) {
                $AuftragCriteria['Rechnung'][] = $rechnung->getRechnungsnummer();
            }

            $letzteAuftraege = $this->auftragRepository->findBy($AuftragCriteria, ['Datum' => 'DESC'], 15);
            $letzteArtikel = $this->artikelRepository->findBy($AuftragCriteria, ['zeitstempel' => 'DESC'],15);
            $letzteRechnungen = $this->rechnungRepository->findBy(['KdNr' => $this->adminContextProvider->getContext()->getUser()->getKdNr()], ['id' => 'DESC'], 15);
        }

        foreach ($letzteAuftraege as $auftrag) {
            $auftrag->targetUrl = $this->adminUrlGenerator
                ->setController(AuftragCrudController::class)
                ->setAction(Crud::PAGE_DETAIL)
                ->setEntityId($auftrag->getId())
                ->generateUrl();
        }


        foreach ($letzteArtikel as $artikel) {
            $artikel->targetUrl = $this->adminUrlGenerator
                ->setController(ArtikelCrudController::class)
                ->setAction(Crud::PAGE_DETAIL)
                ->setEntityId($artikel->getId())
                ->generateUrl();
        }


        foreach ($letzteRechnungen as $rechnung) {
            $rechnung->targetUrl = $this->adminUrlGenerator
                ->setController(RechnungCrudController::class)
                ->setAction(Crud::PAGE_DETAIL)
                ->setEntityId($rechnung->getRechnungsnummer())
                ->generateUrl();
        }

        return $this->render('admin/index.html.twig', [
            'letzteAuftraege' => $letzteAuftraege,
            'letzteArtikel' => $letzteArtikel,
            'letzteRechnungen' => $letzteRechnungen,
            'mitarbeiterListe' => $mitarbeiterListe,
            'grundeListe' => $grundeListe,
            'statusListe' => $statusListe,
            'anzahl' => $anzahl
        ]);

// return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Batterie Reklamation')
            ->generateRelativeUrls();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Homepage', 'fas fa-home', 'app_homepage'); // ->setLinkTarget('_blank')
        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');
        // yield MenuItem::linkToCrud('The Label', 'fas fa-list', EntityClass::class);
        yield MenuItem::section('Admin')->setPermission('ROLE_MITARBEITER');
        yield MenuItem::linkToCrud('Aufkleber', 'fa fa-tags', Aufkleber::class)->setPermission('ROLE_AUFKLEBER');
        yield MenuItem::linkToCrud('E-Mail-Vorlagen', 'fa fa-tags', EmailVorlag::class)->setPermission('ROLE_MITARBEITER');
        yield MenuItem::linkToCrud('Bestellpositionen', 'fa fa-tags', Artikel::class)->setPermission('ROLE_MITARBEITER');
        yield MenuItem::linkToCrud('Basisartikel', 'fa fa-tags', BasisArtikel::class)->setPermission('ROLE_MITARBEITER');
        yield MenuItem::linkToCrud('Auftr&auml;ge', 'fa fa-tags', Auftrag::class)->setPermission('ROLE_MITARBEITER');
        yield MenuItem::linkToCrud('Archivierte Auftr&auml;ge', 'far fa-question-circle', Auftrag::class)
            ->setController(ArchivierterAuftragCrudController::class)->setPermission('ROLE_MITARBEITER');
        yield MenuItem::linkToCrud('Kommentare', 'fa fa-comment-alt', Kommentar::class)->setPermission('ROLE_MITARBEITER');
        yield MenuItem::linkToCrud('Rechnungen', 'fa fa-tags', Rechnung::class)->setPermission('ROLE_MITARBEITER');

        yield MenuItem::section('CMS')->setPermission('ROLE_ADMIN');
        yield MenuItem::subMenu('Design')->setPermission('ROLE_ADMIN')->setSubItems([
            MenuItem::linkToCrud('Folien', 'fa fa-tags', Slide::class)->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('Menus', 'fa fa-tags', Menu::class)->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('Men&uuml;punkte', 'fa fa-tags', MenuePunkt::class)->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('Seiten', 'fa fa-tags', Seite::class)->setPermission('ROLE_ADMIN')
    ]);

        yield MenuItem::subMenu('Inhalt')->setPermission('ROLE_ADMIN')->setSubItems([
            MenuItem::linkToCrud('Fragen', 'fa fa-tags', Frage::class)->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('Gr&uuml;nde', 'fa fa-tags', Grund::class)->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('H&auml;ndler', 'fa fa-tags', Haendler::class)->setPermission('ROLE_ADMIN'),
            MenuItem::linkToUrl('Mitarbeiter', 'fa fa-tags', '?referrer=%2Fadmin%3FcrudAction%3Dindex%26crudControllerFqcn%3DApp%255CController%255CAdmin%255CBenutzerCrudController%26menuIndex%3D12%26submenuIndex%3D3&crudAction=index&crudControllerFqcn=App\Controller\Admin\BenutzerCrudController&menuIndex=12&submenuIndex=3&filters[roles][comparison]=not+like&filters[roles][value]=ROLE_USER')->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('Status-Informationen', 'fa fa-tags', Status::class)->setPermission('ROLE_ADMIN')
        ]);
        yield MenuItem::linkToLogout(t('user.sign_out', domain: 'EasyAdminBundle'), 'fas fa-sign-out-alt');
    }

    public function configureUserMenu(Benutzer|UserInterface $user): UserMenu
    {
        $userMenuItems = [];

        if (class_exists(LogoutUrlGenerator::class)) {
            $userMenuItems[] = MenuItem::section();
            $userMenuItems[] = MenuItem::linkToUrl('Mein Profil', 'fas fa-user', '/admin?crudAction=detail&crudControllerFqcn=App\Controller\Admin\BenutzerCrudController&entityId=' . $user->getId() . '&menuIndex=16&referrer=?crudAction%3Dindex%26crudControllerFqcn%3DApp%255CController%255CAdmin%255CBenutzerCrudController%26menuIndex%3D16%26submenuIndex%3D-1&submenuIndex=-1');
            $userMenuItems[] = MenuItem::linkToLogout(t('user.sign_out', domain: 'EasyAdminBundle'), 'fa-sign-out');
        }
        if ($this->isGranted(Permission::EA_EXIT_IMPERSONATION)) {
            $userMenuItems[] = MenuItem::linkToExitImpersonation(t('user.exit_impersonation', domain: 'EasyAdminBundle'), 'fa-user-lock');
        }

        $userName = '';
        if (method_exists($user, '__toString')) {
            $userName = (string)$user;
        } elseif (method_exists($user, 'getUserIdentifier')) {
            $userName = $user->getUserIdentifier();
        } elseif (method_exists($user, 'getUsername')) {
            $userName = $user->getUsername();
        }

        $avatar = $user->getAvatar() ?? 'no-avatar-350x350-300x300.jpg';

        return UserMenu::new()
            ->displayUserName()
            ->displayUserAvatar()
            ->setName($userName)
            ->setAvatarUrl('/uploads/avatars/' . $avatar)
            ->setMenuItems($userMenuItems);
    }

    /*public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }*/
}
