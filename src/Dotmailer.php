<?php

namespace Dotmailer;

use Dotmailer\Adapter\Adapter;
use Dotmailer\Entity\AddressBook;
use Dotmailer\Entity\Campaign;
use Dotmailer\Entity\Contact;
use Dotmailer\Entity\DataField;
use Dotmailer\Entity\Program;
use Dotmailer\Factory\CampaignFactory;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\json_decode;

class Dotmailer
{
//    const DEFAULT_URI = 'https://r1-api.dotmailer.com';


    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * @var ResponseInterface|null
     */
    private $response;

    /**
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }


    /**
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return AddressBook[]
     */
    public function getAddressBooks(): array
    {
        $this->response = $this->adapter->get('/v2/address-books');
        $addressBooks = [];

        foreach (json_decode($this->response->getBody()->getContents()) as $addressBook) {
            $addressBooks[] = new AddressBook(
                $addressBook->id,
                $addressBook->name,
                $addressBook->visibility,
                $addressBook->contacts
            );
        }

        return $addressBooks;
    }

    /**
     * @return AddressBook[]
     */
    public function getAddressBook(int $id): AddressBook
    {
        $url = sprintf('/v2/address-books/%s', $id);
        $this->response = $this->adapter->get($url);
        $addressBooks = json_decode($this->response->getBody()->getContents());
        return new AddressBook(
            $addressBooks->id,
            $addressBooks->name,
            $addressBooks->visibility,
            $addressBooks->contacts
        );
    }

    public function getAddressBookCampaigns(int $addressBookId, $select = 1000, $skip = 0): array
    {
        $url = sprintf("/v2/address-books/%s/campaigns?select=%s&skip=%s", $addressBookId, $select, $skip);
        $this->response = $this->adapter->get($url);
        $campaigns = [];
        foreach (json_decode($this->response->getBody()->getContents()) as $campaign) {
            $campaigns[] = CampaignFactory::build($campaign);
//            $campaigns[] = new Dotmailer\Entity\StandardCampaign(
//                 $campaign->id,
//                $campaign->name,
//                $campaign->subject,
//                $campaign->fromName,
//                $campaign->htmlContent,
//                $campaign->plainTextContent,
//                $campaign->fromAddress,
//                $campaign->replyAction ,
//                $campaign->replyToAddress,
//                $campaign->status
//            );
        }
        return $campaigns;
    }


    public function getContactsFromAddressBook(int $addressbookID, $withFullData = false, $select = 1000, $skip = 0)
    {
        $url = sprintf(
            '/v2/address-books/%s/contacts?withFullData=%s&select=%s&skip=%s',
            $addressbookID,
            $withFullData,
            $select,
            $skip
        );
        $this->response = $this->adapter->get($url);
        $res = $this->response->getBody()->getContents();
        if ($res === '') {
            $this->response->getBody()->rewind();
            $res = $this->response->getBody()->getContents();
        }
        return json_decode($res, false, 512, JSON_FORCE_OBJECT);
    }


    /**
     * @return Campaign[]
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getAllCampaigns(): array
    {
        $this->response = $this->adapter->get('/v2/campaigns');
        $campaigns = [];

        foreach (json_decode($this->response->getBody()->getContents()) as $campaign) {
            $campaigns[] = CampaignFactory::build($campaign);
        }

        return $campaigns;
    }

    /**
     * @param int $id
     *
     * @return Campaign
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getCampaign(int $id): Campaign
    {
        $this->response = $this->adapter->get('/v2/campaigns/' . $id);

        return CampaignFactory::build(json_decode($this->response->getBody()->getContents()));
    }

    public function getCampaignActivityForContact(Campaign $campaign, Contact $contact): \stdClass
    {
        $url = sprintf("/v2/campaigns/%s/activities/%s", $campaign->getId(), $contact->getId());
        $this->response = $this->adapter->get($url);
        return json_decode($this->response->getBody()->getContents());
    }


    /**
     * @param Contact $contact
     */
    public function createContact(Contact $contact)
    {
        $this->response = $this->adapter->post('/v2/contacts', $contact->asArray());
    }

    /**
     * @param Contact $contact
     */
    public function deleteContact(Contact $contact)
    {
        $this->response = $this->adapter->delete('/v2/contacts/' . $contact->getId());
    }

    /**
     * @param Contact $contact
     */
    public function updateContact(Contact $contact)
    {
        $this->response = $this->adapter->put('/v2/contacts/' . $contact->getId(), $contact->asArray());
    }

    /**
     * @param Contact     $contact
     * @param AddressBook $addressBook
     */
    public function addContactToAddressBook(Contact $contact, AddressBook $addressBook)
    {
        $this->response = $this->adapter->post(
            '/v2/address-books/' . $addressBook->getId() . '/contacts',
            $contact->asArray()
        );
    }

    /**
     * @param Contact     $contact
     * @param AddressBook $addressBook
     */
    public function deleteContactFromAddressBook(Contact $contact, AddressBook $addressBook)
    {
        $this->response = $this->adapter->delete('/v2/address-books/' . $addressBook->getId() . '/contacts/' . $contact->getId());
    }

    /**
     * @param string $email
     *
     * @return Contact
     */
    public function getContactByEmail(string $email): Contact
    {
        $this->response = $this->adapter->get('/v2/contacts/' . $email);

        $contact = json_decode($this->response->getBody()->getContents());

        return new Contact(
            $contact->id,
            $contact->email,
            $contact->optInType,
            $contact->emailType,
            $contact->dataFields,
            $contact->status
        );
    }

    /**
     * @param Contact $contact
     *
     * @return AddressBook[]
     */
    public function getContactAddressBooks(Contact $contact): array
    {
        $url = sprintf('/v2/contacts/%s/address-books', $contact->getId());
        $this->response = $this->adapter->get($url);
        $this->response->getBody()->rewind();
        $addressBooks = [];
        $jsonStr = $this->response->getBody()->getContents();
        if ($jsonStr !== '') {
            $res = json_decode($jsonStr, false, 512, JSON_FORCE_OBJECT);
            foreach ($res as $addressBook) {
                $addressBooks[] = new AddressBook(
                    $addressBook->id,
                    $addressBook->name,
                    $addressBook->visibility,
                    $addressBook->contacts
                );
            }
        }
        return $addressBooks;
    }

    /**
     * @param \DateTimeInterface $dateTime
     * @param int|null           $select
     * @param int|null           $skip
     *
     * @return Contact[]
     */
    public function getUnsubscribedContactsSince(
        \DateTimeInterface $dateTime,
        int $select = null,
        int $skip = null
    ): array {
        $this->response = $this->adapter->get(
            '/v2/contacts/unsubscribed-since/' . $dateTime->format('Y-m-d'),
            array_filter([
                'select' => $select,
                'skip' => $skip,
            ])
        );

        $unsubscriptions = [];

        foreach (json_decode($this->response->getBody()->getContents()) as $unsubscription) {
            $unsubscriptions[] = [
                'suppressedContact' => new Contact(
                    $unsubscription->suppressedContact->id,
                    $unsubscription->suppressedContact->email,
                    $unsubscription->suppressedContact->optInType,
                    $unsubscription->suppressedContact->emailType
                ),
                'dateRemoved' => new \DateTime($unsubscription->dateRemoved),
                'reason' => $unsubscription->reason,
            ];
        }

        return $unsubscriptions;
    }

    /**
     * @param Contact $contact
     */
    public function unsubscribeContact(Contact $contact)
    {
        $this->response = $this->adapter->post('/v2/contacts/unsubscribe', ['email' => $contact->getEmail()]);
    }

    /**
     * @param Contact     $contact
     * @param string|null $preferredLocale
     * @param string|null $challengeUrl
     */
    public function resubscribeContact(
        Contact $contact,
        string $preferredLocale = null,
        string $challengeUrl = null
    ) {
        $content = [
            'unsubscribedContact' => [
                'email' => $contact->getEmail(),
            ],
            'preferredLocale' => $preferredLocale,
            'returnUrlToUseIfChallenged' => $challengeUrl,
        ];

        $this->response = $this->adapter->post('/v2/contacts/resubscribe', array_filter($content));
    }

    /**
     * @param Contact     $contact
     * @param string|null $preferredLocale
     * @param string|null $challengeUrl
     */
    public function resubscribeContactNoChallenge(
        Contact $contact,
        string $preferredLocale = null,
        string $challengeUrl = null
    ) {
        $content = [
            'unsubscribedContact' => [
                'email' => $contact->getEmail(),
                'dataFields' => $contact->getDataFields(),
            ],
            'preferredLocale' => $preferredLocale,
            'returnUrlToUseIfChallenged' => $challengeUrl,
        ];

        $this->response = $this->adapter->post(
            '/v2/contacts/resubscribe-with-no-challenge',
            array_filter($content)
        );
    }

    public function resubscribeContactNoChallengeToAddressbook(
        Contact $contact,
        int $addressbookId,
        string $preferredLocale = null,
        string $challengeUrl = null
    ) : void {
        $content = [
            'unsubscribedContact' => [
                'email' => $contact->getEmail(),
                'dataFields' => $contact->getDataFields(),
            ],
            'preferredLocale' => $preferredLocale,
            'returnUrlToUseIfChallenged' => $challengeUrl,
        ];
        $url = sprintf('/v2/address-books/%s/contacts/resubscribe-with-no-challenge', $addressbookId);
        $this->response = $this->adapter->post(
            $url,
            array_filter($content)
        );
    }

    /**
     * @param DataField $dataField
     */
    public function createContactDataField(DataField $dataField)
    {
        $this->response = $this->adapter->post('/v2/data-fields', $dataField->asArray());
    }

    /**
     * @param DataField $dataField
     */
    public function deleteContactDataField(DataField $dataField)
    {
        $this->response = $this->adapter->delete('/v2/data-fields/' . $dataField->getName());
    }

    /**
     * @return Program[]
     */
    public function getPrograms(): array
    {
        $this->response = $this->adapter->get('/v2/programs');
        $programs = [];

        foreach (json_decode($this->response->getBody()->getContents()) as $program) {
            $programs[] = new Program(
                $program->id,
                $program->name,
                $program->status,
                new \DateTimeImmutable($program->dateCreated)
            );
        }

        return $programs;
    }

    /**
     * getProgramById
     *
     * @param int $id
     *
     * @return \Dotmailer\Entity\Program
     * @throws \Exception
     */
    public function getProgramById(int $id): array
    {
        $url = sprintf("/v2/programs/%s", $id);
        $this->response = $this->adapter->get($url);
        $programs = [];
        $programObj = json_decode($this->response->getBody()->getContents());
        $program = new Program(
            $programObj->id,
            $programObj->name,
            $programObj->status,
            new \DateTimeImmutable($programObj->dateCreated)
        );

        return $program;
    }

    /**
     * @param Program       $program
     * @param Contact[]     $contacts
     * @param AddressBook[] $addressBooks
     */
    public function createProgramEnrolment(Program $program, array $contacts = [], array $addressBooks = [])
    {
        $this->response = $this->adapter->post('/v2/programs/enrolments', [
                'programId' => $program->getId(),
                'contacts' => array_map(function (Contact $contact) {
                    return $contact->getId();
                }, $contacts),
                'addressBooks' => array_map(function (AddressBook $addressBook) {
                    return $addressBook->getId();
                }, $addressBooks),
            ]);
    }

    /**
     * @param string[] $toAddresses
     * @param string   $subject
     * @param string   $fromAddress
     * @param string   $htmlContent
     * @param string   $plainTextContent
     * @param string[] $ccAddresses
     * @param string[] $bccAddresses
     */
    public function sendTransactionalEmail(
        array $toAddresses,
        string $subject,
        string $fromAddress,
        string $htmlContent,
        string $plainTextContent,
        array $ccAddresses = [],
        array $bccAddresses = []
    ) {
        $this->response = $this->adapter->post('/v2/email', [
                'toAddresses' => $toAddresses,
                'subject' => $subject,
                'fromAddress' => $fromAddress,
                'htmlContent' => $htmlContent,
                'plainTextContent' => $plainTextContent,
                'ccAddresses' => $ccAddresses,
                'bccAddresses' => $bccAddresses,
            ]);
    }

    /**
     * @param string[] $toAddresses
     * @param int      $campaignId
     * @param string[] $personalizationValues
     */
    public function sendTransactionalEmailUsingATriggeredCampaign(
        array $toAddresses,
        int $campaignId,
        array $personalizationValues
    ) {
        $this->response = $this->adapter->post('/v2/email/triggered-campaign', [
                'toAddresses' => $toAddresses,
                'campaignId' => $campaignId,
                'personalizationValues' => array_map(function (string $name, $value) {
                    return [
                        'Name' => strtoupper($name),
                        'Value' => $value,
                    ];
                }, array_keys($personalizationValues), $personalizationValues),
            ]);
    }
}
