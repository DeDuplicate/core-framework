<?php

namespace Webkul\UVDesk\CoreBundle\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Common\Collections\Criteria;
use Webkul\UVDesk\CoreBundle\Entity\User;
use Webkul\UVDesk\CoreBundle\Entity\Ticket;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * TicketRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TicketRepository extends \Doctrine\ORM\EntityRepository
{
    const LIMIT = 15;
    const DEFAULT_PAGINATION_LIMIT = 15;

    public $params = [];
    private $container;
    private $requestStack;
    private $safeFields = ['page', 'limit', 'sort', 'order', 'direction'];

    public function getTicketLabelCollection(Ticket $ticket, User $user)
    {
        // $queryBuilder = $this->getEntityManager()->createQueryBuilder()
        //     ->select("DISTINCT supportLabel.id, supportLabel.name, supportLabel.colorCode as color")
        //     ->from('UVDeskCoreBundle:Ticket', 'ticket')
        //     ->leftJoin('ticket.supportLabels', 'supportLabel')
        //     // ->leftJoin('supportLabel.user', 'user')
        //     ->where('ticket.id = :ticketId')->setParameter('ticketId', $ticket->getId())
        //     ->andWhere('supportLabel.user = :user')->setParameter('user', $user);

        return [];
    }

    public function getAllTickets(\Symfony\Component\HttpFoundation\ParameterBag $obj = null, $container, $actAsUser = null)
    {
        $currentUser = $actAsUser ? : $container->get('user.service')->getCurrentUser();
  
        $json = array();
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('DISTINCT t,gr,pr,tp,s,a.id as agentId,c.id as customerId')->from($this->getEntityName(), 't');
        $qb->leftJoin('t.agent', 'a');
        $qb->leftJoin('a.userInstance', 'ad');
        $qb->leftJoin('t.status', 's');
        $qb->leftJoin('t.customer', 'c');
        $qb->leftJoin('t.supportGroup', 'gr');
        $qb->leftJoin('t.priority', 'pr');
        $qb->leftJoin('t.type', 'tp');
        // $qb->leftJoin('t.collaborators', 'tc');
        $qb->addSelect("CONCAT(a.firstName,' ', a.lastName) AS name");
        $qb->andwhere("t.agent IS NULL OR ad.supportRole != 4");
        $data = $obj->all();
        $data = array_reverse($data);
        foreach ($data as $key => $value) {
            if(!in_array($key,$this->safeFields)) {
                if(isset($data['search']) && $key == 'search') {
                    $qb->andwhere("t.subject LIKE :subject OR a.email LIKE :agentName OR t.id LIKE :ticketId");
                    $qb->setParameter('subject', '%'.urldecode($value).'%');
                    $qb->setParameter('agentName', '%'.urldecode($value).'%');
                    $qb->setParameter('ticketId', '%'.urldecode($value).'%');
                } elseif($key == 'status') {
                    $qb->andwhere('t.status = '.intval($value));
                }
            }
        }
        $qb->andwhere('t.isTrashed != 1');

        if(!isset($data['sort'])) {
            $qb->orderBy('t.id',Criteria::DESC);
        }

        $paginator = $container->get('knp_paginator');

        $newQb = clone $qb;
        $newQb->select('COUNT(DISTINCT t.id)');

        $results = $paginator->paginate(
            $qb->getQuery()->setHydrationMode(Query::HYDRATE_ARRAY)->setHint('knp_paginator.count', $newQb->getQuery()->getSingleScalarResult()),
            isset($data['page']) ? $data['page'] : 1,
            self::LIMIT,
            array('distinct' => true)
        );

        $paginationData = $results->getPaginationData();
        $queryParameters = $results->getParams();

        $queryParameters['page'] = "replacePage";
        $paginationData['url'] = '#'.$container->get('uvdesk.service')->buildPaginationQuery($queryParameters);

        $data = array();
        $userService = $container->get('user.service');
        $ticketService = $container->get('ticket.service');
        $translatorService = $container->get('translator');

        foreach ($results as $key => $ticket) {
            $ticket[0]['status']['code'] = $translatorService->trans($ticket[0]['status']['code']);

            $data[] = [
                'id' => $ticket[0]['id'],
                'subject' => $ticket[0]['subject'],
                'isCustomerView' => $ticket[0]['isCustomerViewed'],
                'status' => $ticket[0]['status'],
                'group' => $ticket[0]['supportGroup'],
                'type' => $ticket[0]['type'],
                'priority' => $ticket[0]['priority'],
                'formatedCreatedAt' => $userService->convertToTimezone($ticket[0]['createdAt']),
                'totalThreads' => $ticketService->getTicketTotalThreads($ticket[0]['id']),
                'agent' => $ticket['agentId'] ? $userService->getAgentPartialDetailById($ticket['agentId']) : null,
                'customer' => $ticket['customerId'] ? $userService->getCustomerPartialDetailById($ticket['customerId']) : null,
                // 'hasAttachments' => $ticketService->hasAttachments($ticket[0]['id'])
            ];
        }

        $json['tickets'] = $data;
        $json['pagination'] = $paginationData;

        return $json;
    }

    public function getAllCustomerTickets(\Symfony\Component\HttpFoundation\ParameterBag $obj = null, $container, $actAsUser = null) {
       
        $currentUser = $actAsUser ? : $container->get('user.service')->getCurrentUser();
        $json = array();
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('DISTINCT t,gr,pr,tp,s,a.id as agentId,c.id as customerId')->from($this->getEntityName(), 't');
        $qb->leftJoin('t.agent', 'a');
        $qb->leftJoin('a.userInstance', 'ad');
        $qb->leftJoin('t.status', 's');
        $qb->leftJoin('t.customer', 'c');
        $qb->leftJoin('t.supportGroup', 'gr');
        $qb->leftJoin('t.priority', 'pr');
        $qb->leftJoin('t.type', 'tp');
        // $qb->leftJoin('t.collaborators', 'tc');
        $qb->addSelect("CONCAT(a.firstName,' ', a.lastName) AS name");
        $qb->andwhere("t.agent IS NULL OR ad.supportRole != 4");

        $data = $obj->all();
        $data = array_reverse($data);
        foreach ($data as $key => $value) {
            if(!in_array($key,$this->safeFields)) {
                if(isset($data['search']) && $key == 'search') {
                    $qb->andwhere("t.subject LIKE :subject OR a.email LIKE :agentName OR t.id LIKE :ticketId");
                    $qb->setParameter('subject', '%'.urldecode($value).'%');
                    $qb->setParameter('agentName', '%'.urldecode($value).'%');
                    $qb->setParameter('ticketId', '%'.urldecode($value).'%');
                } elseif($key == 'status') {
                    $qb->andwhere('t.status = '.intval($value));
                }
            }
        }

        $qb->andwhere('t.customer = :customerId');
        $qb->setParameter('customerId', $currentUser->getId());
        $qb->andwhere('t.isTrashed != 1');

        if(!isset($data['sort'])) {
            $qb->orderBy('t.id',Criteria::DESC);
        }

        $paginator = $container->get('knp_paginator');

        $newQb = clone $qb;
        $newQb->select('COUNT(DISTINCT t.id)');

        $results = $paginator->paginate(
            $qb->getQuery()->setHydrationMode(Query::HYDRATE_ARRAY)->setHint('knp_paginator.count', $newQb->getQuery()->getSingleScalarResult()),
            isset($data['page']) ? $data['page'] : 1,
            self::LIMIT,
            array('distinct' => true)
        );

        $paginationData = $results->getPaginationData();
        $queryParameters = $results->getParams();

        $queryParameters['page'] = "replacePage";
        $paginationData['url'] = '#'.$container->get('uvdesk.service')->buildPaginationQuery($queryParameters);

        $data = array();
        $userService = $container->get('user.service');
        $ticketService = $container->get('ticket.service');
        $translatorService = $container->get('translator');

        foreach ($results as $key => $ticket) {
            $ticket[0]['status']['code'] = $translatorService->trans($ticket[0]['status']['code']);

            $data[] = [
                'id' => $ticket[0]['id'],
                'subject' => $ticket[0]['subject'],
                'isCustomerView' => $ticket[0]['isCustomerViewed'],
                'status' => $ticket[0]['status'],
                'group' => $ticket[0]['supportGroup'],
                'type' => $ticket[0]['type'],
                'priority' => $ticket[0]['priority'],
                'totalThreads' => $ticketService->getTicketTotalThreads($ticket[0]['id']),
                'agent' => $ticket['agentId'] ? $userService->getAgentDetailById($ticket['agentId']) : null,
                'customer' => $ticket['customerId'] ? $userService->getCustomerPartialDetailById($ticket['customerId']) : null,
                'formatedCreatedAt' => $ticket[0]['createdAt']->format('d-m-Y h:ia'),
            ];
        }

        $json['tickets'] = $data;
        $json['pagination'] = $paginationData;

        return $json;
    }

    public function prepareBaseTicketQuery(User $user, array $params, $filterByStatus = true)
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select("
                DISTINCT ticket,
                supportGroup.name as groupName, 
                supportTeam.name as teamName, 
                priority, 
                type.code as typeName, 
                agent.id as agentId, 
                agentInstance.profileImagePath as smallThumbnail, 
                customer.id as customerId, 
                customer.email as customerEmail, 
                customerInstance.profileImagePath as customersmallThumbnail, 
                CONCAT(customer.firstName, ' ', customer.lastName) AS customerName, 
                CONCAT(agent.firstName,' ', agent.lastName) AS agentName
            ")
            ->from('UVDeskCoreBundle:Ticket', 'ticket')
            ->leftJoin('ticket.agent', 'agent')
            ->leftJoin('ticket.customer', 'customer')
            ->leftJoin('ticket.supportGroup', 'supportGroup')
            ->leftJoin('ticket.supportTeam', 'supportTeam')
            ->leftJoin('ticket.priority', 'priority')
            ->leftJoin('ticket.type', 'type')
            ->leftJoin('customer.userInstance', 'customerInstance')
            ->leftJoin('agent.userInstance', 'agentInstance')
            ->where('customerInstance.supportRole = 4')
            ->andWhere("ticket.agent IS NULL OR agentInstance.supportRole != 4")
            ->andWhere('ticket.isTrashed = :isTrashed')->setParameter('isTrashed', isset($params['trashed']) ? true : false);

        if (!isset($params['sort'])) {
            $queryBuilder->orderBy('ticket.updatedAt', Criteria::DESC);
        }

        if ($filterByStatus) {
            $queryBuilder->andWhere('ticket.status = :status')->setParameter('status', isset($params['status']) ? $params['status'] : 1);
        }

        foreach ($params as $field => $fieldValue) {
            if (in_array($field, $this->safeFields)) {
                continue;
            }


            switch ($field) {
                // case 'label':
                //     $queryBuilder->leftJoin('t.ticketLabels', 'tl');
                //     $queryBuilder->andwhere('tl.id IN (:labelIds)');
                //     $queryBuilder->setParameter('labelIds', array($value));
                //     break;
                case 'starred':
                    $queryBuilder->andWhere('ticket.isStarred = 1');
                    break;
                // case 'search':
                //     $queryBuilder->andwhere("ticket.subject LIKE :subject OR c.email LIKE :customerEmail OR CONCAT(cd.firstName,' ', cd.lastName) LIKE :customerName OR a.email LIKE :agentEmail OR CONCAT(ad.firstName,' ', ad.lastName) LIKE :agentName OR t.incrementId LIKE :ticketId");
                //     $queryBuilder->setParameter('subject', '%'.urldecode($value).'%');
                //     $queryBuilder->setParameter('customerName', '%'.urldecode($value).'%');
                //     $queryBuilder->setParameter('customerEmail', '%'.urldecode($value).'%');
                //     $queryBuilder->setParameter('agentName', '%'.urldecode($value).'%');
                //     $queryBuilder->setParameter('agentEmail', '%'.urldecode($value).'%');
                //     $queryBuilder->setParameter('ticketId', '%'.urldecode(trim($value)).'%');
                //     break;
                case 'unassigned':
                    $queryBuilder->andWhere("ticket.agent is NULL");
                    break;
                case 'notreplied':
                    $queryBuilder->andWhere('ticket.isReplied = 0');
                    break;
                case 'mine':
                    $queryBuilder->andWhere('ticket.agent = :agentId')->setParameter('agentId', $user->getId());
                    break;
                case 'new':
                    $queryBuilder->andwhere('ticket.isNew = 1');
                    break;
                case 'priority':
                    $queryBuilder->andwhere('priority.id IN (:priorities)')->setParameter('priorities', explode(',', $fieldValue));
                    break;
                case 'type':
                    $queryBuilder->andwhere('type.id IN (:typeCollection)')->setParameter('typeCollection', explode(',', $fieldValue));
                    break;
                case 'agent':
                    $queryBuilder->andwhere('agent.id IN (:agentCollection)')->setParameter('agentCollection', explode(',', $fieldValue));
                    break;
                case 'customer':
                    $queryBuilder->andwhere('customer.id IN (:customerCollection)')->setParameter('customerCollection', explode(',', $fieldValue));
                    break;
                // case 'group':
                //     $queryBuilder->andwhere('gr.id IN (:groupIds)');
                //     $queryBuilder->setParameter('groupIds', explode(',', $fieldValue));
                //     break;
                // case 'team':
                //     $queryBuilder->andwhere("tSub.id In(:subGrpKeys)");
                //     $queryBuilder->setParameter('subGrpKeys', explode(',', $fieldValue));
                //     break;
                // case 'tag':
                //     $queryBuilder->leftJoin('t.tags', 'tg');
                //     $queryBuilder->andwhere("tg.id In(:tagIds)");
                //     $queryBuilder->setParameter('tagIds', explode(',', $fieldValue));
                //     break;
                // case 'mailbox':
                //     $queryBuilder->leftJoin('t.mailbox', 'ml');
                //     $queryBuilder->andwhere('ml.id IN (:mailboxIds)');
                //     $queryBuilder->setParameter('mailboxIds', explode(',', $fieldValue));
                //     break;
                // case 'source':
                //     $queryBuilder->andwhere('t.source IN (:sources)');
                //     $queryBuilder->setParameter('sources', explode(',', $fieldValue));
                //     break;
                // case 'after':
                //     $date = \DateTime::createFromFormat('d-m-Y H:i', $fieldValue.' 23:59');
                //     if($date) {
                //         $date = \DateTime::createFromFormat('d-m-Y H:i', $container->get('user.service')->convertTimezoneToServer($date, 'd-m-Y H:i'));
                //         $queryBuilder->andwhere('t.createdAt > :afterDate');
                //         $queryBuilder->setParameter('afterDate', $date);
                //     }
                //     break;
                // case 'before':
                //     $date = \DateTime::createFromFormat('d-m-Y H:i', $fieldValue.' 23:59');
                //     if($date) {
                //         $date = \DateTime::createFromFormat('d-m-Y H:i', $container->get('user.service')->convertTimezoneToServer($date, 'd-m-Y H:i'));
                //         $queryBuilder->andwhere('t.createdAt < :beforeDate');
                //         $queryBuilder->setParameter('beforeDate', $date);
                //     }
                //     break;
                // case 'custom':
                //     $queryBuilder->leftJoin('t.customFieldValues', 'tCfV');
                //     $columnSeparator = '|';
                //     $indexValueSeparator = '_';
                //     $customFields = explode($columnSeparator, $fieldValue);
                //     $query = [];
                //     foreach ($customFields as $key => $customField) {
                //         $idValue = explode($indexValueSeparator, $customField);
                //         if(isset($idValue[1])){
                //             $query[] = '(tCfV.ticketCustomFieldsValues = :customId'. $key .' AND (tCfV.value LIKE :customValue'. $key .' OR tCfV.ticketCustomFieldValueValues IN (:customValueId'. $key .')))';
                //             $queryBuilder->setParameter('customId'.$key , $idValue[0]);
                //             $queryBuilder->setParameter('customValue'.$key , '%'.$idValue[1].'%');
                //             $queryBuilder->setParameter('customValueId'.$key , explode(',', $idValue[1]));
                //         }
                //     }

                //     if($query) {
                //         $queryBuilder->andwhere(implode(' OR ', $query));
                //     }
                //     break;
                default:
                    break;
            }
        }

        return $queryBuilder;
    }

    public function prepareBasePaginationTicketTypesQuery(array $params)
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select("ticketType")
            ->from('UVDeskCoreBundle:TicketType', 'ticketType');

        // Apply filters
        foreach ($params as $field => $fieldValue) {
            if (in_array($field, $this->safeFields)) {
                continue;
            }
            switch ($field) {
                case 'search':
                    $queryBuilder->andwhere("ticketType.code LIKE :searchQuery OR ticketType.description LIKE :searchQuery");
                    $queryBuilder->setParameter('searchQuery', '%' . urldecode($fieldValue) . '%');
                    break;
                case 'isActive':
                    $queryBuilder->andwhere("ticketType.isActive LIKE :searchQuery");
                    $queryBuilder->setParameter('searchQuery', '%' . urldecode($fieldValue) . '%');
                break;
                default:
                    break;
            }
        }

        // Define sort by
        if (empty($params['sort']) || 'a.id' == $params['sort']) {
            $queryBuilder->orderBy('ticketType.id', (empty($params['direction']) || 'ASC' == strtoupper($params['direction'])) ? Criteria::ASC : Criteria::DESC);
        } else {
            $queryBuilder->orderBy('ticketType.code', (empty($params['direction']) || 'ASC' == strtoupper($params['direction'])) ? Criteria::ASC : Criteria::DESC);
        }

        return $queryBuilder;
    }

    public function prepareBasePaginationTagsQuery(array $params)
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('supportTag.id as id, supportTag.name as name, COUNT(ticket) as totalTickets')
            ->from('UVDeskCoreBundle:Tag', 'supportTag')
            ->leftJoin('supportTag.tickets', 'ticket')
            ->groupBy('supportTag.id');

        // Apply filters
        foreach ($params as $field => $fieldValue) {
            if (in_array($field, $this->safeFields)) {
                continue;
            }

            switch ($field) {
                case 'search':
                    $queryBuilder->andwhere("supportTag.name LIKE :searchQuery")->setParameter('searchQuery', '%' . urldecode($fieldValue) . '%');
                    break;
                default:
                    break;
            }
        }

        // Define sort by
        if (empty($params['sort']) || 'a.id' == $params['sort']) {
            $queryBuilder->orderBy('supportTag.id', (empty($params['direction']) || 'ASC' == strtoupper($params['direction'])) ? Criteria::ASC : Criteria::DESC);
        } else {
            $queryBuilder->orderBy('supportTag.name', (empty($params['direction']) || 'ASC' == strtoupper($params['direction'])) ? Criteria::ASC : Criteria::DESC);
        }

        return $queryBuilder;
    }

    public function getTicketTabDetails( array $params)
    {
        $data = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
       
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select("
            DISTINCT ticket,
            supportGroup.name as groupName, 
            supportTeam.name as teamName, 
            priority, 
            type.code as typeName, 
            agent.id as agentId, 
            agentInstance.profileImagePath as smallThumbnail, 
            customer.id as customerId, 
            customer.email as customerEmail, 
            customerInstance.profileImagePath as customersmallThumbnail, 
            CONCAT(customer.firstName, ' ', customer.lastName) AS customerName, 
            CONCAT(agent.firstName,' ', agent.lastName) AS agentName
        ")
        ->from('UVDeskCoreBundle:Ticket', 'ticket')
        ->leftJoin('ticket.agent', 'agent')
        ->leftJoin('ticket.customer', 'customer')
        ->leftJoin('ticket.supportGroup', 'supportGroup')
        ->leftJoin('ticket.supportTeam', 'supportTeam')
        ->leftJoin('ticket.priority', 'priority')
        ->leftJoin('ticket.type', 'type')
        ->leftJoin('customer.userInstance', 'customerInstance')
        ->leftJoin('agent.userInstance', 'agentInstance')
        ->where('customerInstance.supportRole = 4')
        ->andWhere("ticket.agent IS NULL OR agentInstance.supportRole != 4")
        ->andWhere('ticket.isTrashed = :isTrashed')->setParameter('isTrashed', isset($params['trashed']) ? true : false);

        $queryBuilder->select('COUNT(DISTINCT ticket.id) as countTicket,s.id as statusId,s.code as tab')
                        ->leftJoin('ticket.status', 's')
                        ->groupBy('ticket.status');       
        $results = $queryBuilder->getQuery()->getResult();

        foreach($results as $status) {
            $data[$status['statusId']] = $status['countTicket'];
        }
        return $data;
    }

    public function countTicketTotalThreads($ticketId, $threadType = 'reply')
    {
        $totalThreads = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(thread.id) as threads')
            ->from('UVDeskCoreBundle:Ticket', 'ticket')
            ->leftJoin('ticket.threads', 'thread')
            ->where('ticket.id = :ticketId')->setParameter('ticketId', $ticketId)
            ->andWhere('thread.threadType = :threadType')->setParameter('threadType', $threadType)
            ->getQuery()->getSingleScalarResult();
        
        return (int) $totalThreads;
    }

    public function getTicketNavigationIteration($ticketId)
    {
        // $data = $this->container->get('default.service')->getParamsFromSessionUrl();
        // $qb = $this->em->getRepository('UVDeskCoreBundle:Ticket')
        //            ->getAllTicketsQuery($data, $this->container);

        // if($primaryKey)
        //     $qb->select('DISTINCT t.id as ticketId');
        // else
        //     $qb->select('DISTINCT t.incrementId as ticketId');
        // $qb->addSelect("CONCAT(ad.firstName,' ', ad.lastName) AS agentName");
        // $qb->addSelect("CONCAT(cd.firstName,' ', cd.lastName) AS name");

        // if(isset($data['sort']))
        //     $qb->orderBy($data['sort'],strtoupper($data['direction']));

        // $results = $qb->getQuery()->getResult();
        // $nextPrevPage = array('next' => 0,'prev' => 0);
        // for ($i = 0; $i < count($results); $i++) {
        //     if($results[$i]['ticketId'] == $ticketId) {
        //         $nextPrevPage['next'] = isset($results[$i + 1]) ? $results[$i + 1]['ticketId'] : 0;
        //         $nextPrevPage['prev'] = isset($results[$i - 1]) ? $results[$i - 1]['ticketId'] : 0;
        //     }
        // }
        // return $nextPrevPage;

        return ['next' => 0, 'prev' => 0];
    }

    public function countCustomerTotalTickets(User $user)
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(ticket.id) as tickets')
            ->from('UVDeskCoreBundle:Ticket', 'ticket')
            ->where('ticket.customer = :user')->setParameter('user', $user)
            ->andWhere('ticket.isTrashed != :isTrashed')->setParameter('isTrashed', false);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function isLabelAlreadyAdded($ticket,$label)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('COUNT(t.id) as ticketCount')->from("UVDeskCoreBundle:Ticket", 't')
                ->leftJoin('t.supportLabels','tl')
                ->andwhere('tl.id = :labelId')
                ->andwhere('t.id = :ticketId')
                ->setParameter('labelId',$label->getId())
                ->setParameter('ticketId',$ticket->getId());

        return $qb->getQuery()->getSingleScalarResult() ? true : false;
    }
    
}
