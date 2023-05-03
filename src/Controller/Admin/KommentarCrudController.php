<?php

namespace App\Controller\Admin;

use App\Entity\Auftrag;
use App\Entity\Benutzer;
use App\Entity\Kommentar;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\Translation\t;

class KommentarCrudController extends AbstractCrudController
{
    private AdminContextProvider $adminContextProvider;
    private ManagerRegistry $entityManager;
    private AdminUrlGenerator $adminUrlGenerator;
    private HttpClientInterface $client;
    private NotifierInterface $notifier;
    private Security $security;

    public function __construct(AdminContextProvider $adminContextProvider, ManagerRegistry $entityManager, AdminUrlGenerator $adminUrlGenerator, HttpClientInterface $client, NotifierInterface $notifier, Security $security) {
        $this->adminContextProvider = $adminContextProvider;
        $this->entityManager = $entityManager;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->client = $client;
        $this->notifier = $notifier;
        $this->security = $security;
    }

    public static function getEntityFqcn(): string
    {
        return Kommentar::class;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $Benutzer = $this->security->getUser();


        if ($this->adminContextProvider->getContext()->getRequest()->get('AuftragId'))
        {
            $auftrag = $this->entityManager->getRepository(Auftrag::class)->find($this->adminContextProvider->getContext()->getRequest()->get('AuftragId'));
            $entityInstance->setAuftrag($auftrag);

            if ($Benutzer !== $auftrag->getMitarbeiter()) {
                $this->client->request('GET', 'https://turboemma.de8.quickconnect.to/direct/webapi/entry.cgi?api=SYNO.Chat.External&method=chatbot&version=2&token=EUZUAzSMahLGUzjYVvtg6T86OFpsHrvjgS3cnRu6pC6j2zneksQpaIVpXGOtNVFV&payload={%22text%22:%20%22'.$Benutzer->getName().' hat einen Kommentar auf dem Auftrag <https%3A%2F%2Ftest.batterie-reklamation.de%2Fadmin%3FcrudAction%3Ddetail%26crudControllerFqcn%3DApp%255CController%255CAdmin%255CAuftragCrudController%26entityId%3D'.$auftrag->getId().'%26menuIndex%3D6%26referrer%3D%3FcrudAction%253Dindex%2526crudControllerFqcn%253DApp%25255CController%25255CAdmin%25255CAuftragCrudController%2526menuIndex%253D6%2526submenuIndex%253D-1%26submenuIndex%3D-1|No.'.$auftrag->getId().'> hinzugefügt!%22%2C"user_ids"%3A[27]}');
                //$this->client->request('GET', 'https://turboemma.de8.quickconnect.to/direct/webapi/entry.cgi?api=SYNO.Chat.External&method=incoming&version=2&token=%22zbIUA1nCEZWx4AjZBd3VxTCY77KwinaBnAk5y8m2grEgKEK019DBlOR2NoX799y8%22&payload={%22text%22:%20%22%40'.$auftrag->getMitarbeiter()->getName().', '.$Benutzer->getName().' hat einen Kommentar auf dem Auftrag <https%3A%2F%2Ftest.batterie-reklamation.de%2Fadmin%3FcrudAction%3Ddetail%26crudControllerFqcn%3DApp%255CController%255CAdmin%255CAuftragCrudController%26entityId%3D'.$auftrag->getId().'%26menuIndex%3D6%26referrer%3D%3FcrudAction%253Dindex%2526crudControllerFqcn%253DApp%25255CController%25255CAdmin%25255CAuftragCrudController%2526menuIndex%253D6%2526submenuIndex%253D-1%26submenuIndex%3D-1|No.'.$auftrag->getId().'> hinzugefügt!%22}');
            } else {
                $notification = (new Notification('Ein neuer Kommentar auf Ihrem Auftrag'))
                    ->content($auftrag->getMitarbeiter()->getName()." hat einen Kommentar auf Ihrem Auftrag hinzugefügt!\r\n\r\nhttps://test.batterie-reklamation.de/admin?AuftragId=2&crudAction=detail&crudControllerFqcn=App%5CController%5CAdmin%5CAuftragCrudController&entityId=".$auftrag->getId()."&menuIndex=5&referrer=?crudAction%3Dindex%26crudControllerFqcn%3DApp%255CController%255CAdmin%255CKommentarCrudController%26menuIndex%3D5%26submenuIndex%3D-1&submenuIndex=-1")
                    ->importance(Notification::IMPORTANCE_HIGH);

                $this->notifier->send($notification, new Recipient('dragut@siga-batterien.de')); // $auftrag->getRechnung()->getEmail()
            }
        }

        $entityInstance->setVerfasser($Benutzer);
        $entityInstance->setErstelltAm(new \DateTime());

        $entityManager->persist($entityInstance);
        $entityManager->flush();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('Verfasser')->hideWhenUpdating()->hideWhenCreating(),
            TextareaField::new('Inhalt'),
            AssociationField::new('Auftrag')->hideWhenUpdating()->hideWhenCreating()
            ];
    }

    public function configureCrud(\EasyCorp\Bundle\EasyAdminBundle\Config\Crud $crud): \EasyCorp\Bundle\EasyAdminBundle\Config\Crud
    {
        return $crud
            // the names of the Doctrine entity properties where the search is made on
            // (by default it looks for in all properties)
            ->setSearchFields(['Inhalt', 'Verfasser', 'Auftrag'])

            // defines the initial sorting applied to the list of entities
            // (user can later change this sorting by clicking on the table columns)
            // ->setDefaultSort(['Id' => 'ASC'])

            // the max number of entities to display per page
            ->setPaginatorPageSize(15)
            // the number of pages to display on each side of the current page
            // e.g. if num pages = 35, current page = 7, and you set ->setPaginatorRangeSize(4)
            // the paginator displays: [Previous]  1 ... 3  4  5  6  [7]  8  9  10  11 ... 35  [Next]
            // set this number to 0 to display a simple "< Previous | Next >" pager
            ->setPaginatorRangeSize(4)

            // these are advanced options related to Doctrine Pagination
            // (see https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/tutorials/pagination.html)
            ->setPaginatorUseOutputWalkers(true)
            ->setPaginatorFetchJoinCollection(true)
//            ->showEntityActionsAsDropdown()
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_NEW,Action::SAVE_AND_ADD_ANOTHER);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('Inhalt')
            ->add('Verfasser')
            ->add('Auftrag');
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'];

        $AuftragID = $context->getEntity()->getInstance()->getAuftrag()->getId();

        if (Action::SAVE_AND_RETURN === $submitButtonName) {
            $url = $this->container->get(AdminUrlGenerator::class)->setController(AuftragCrudController::class)->setAction(Action::DETAIL)->setEntityId($AuftragID)->generateUrl();

            return $this->redirect($url);
        }

        return $this->redirectToRoute($context->getDashboardRouteName());
    }
}
