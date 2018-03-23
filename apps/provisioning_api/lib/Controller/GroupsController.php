<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tom Needham <tom@owncloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Provisioning_API\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUser;
use OCA\Provisioning_API\Controller\UsersController;


class GroupsController extends OCSController {

	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserSession */
	private $userSession;

	/** @var ILogger */
	private $logger;

	/** @var UsersController */
	private $userController;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IGroupManager $groupManager
	 * @param IUserSession $userSession
	 * @param ILogger $logger
	 * @param UsersController $userController
	 */
	public function __construct(
			string $appName,
			IRequest $request,
			IGroupManager $groupManager,
			IUserSession $userSession,
			ILogger $logger,
			UsersController $userController) {
		parent::__construct($appName, $request);

		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
		$this->logger = $logger;
		$this->userController = $userController;
	}

	/**
	 * returns a list of groups
	 *
	 * @NoAdminRequired
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return DataResponse
	 */
	public function getGroups(string $search = '', $limit = null, $offset = null): DataResponse {
		if ($limit !== null) {
			$limit = (int)$limit;
		}
		if ($offset !== null) {
			$offset = (int)$offset;
		}

		$groups = $this->groupManager->search($search, $limit, $offset);
		$groups = array_map(function($group) {
			/** @var IGroup $group */
			return $group->getGID();
		}, $groups);

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * returns a list of groups details with ids and displaynames
	 *
	 * @NoAdminRequired
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return DataResponse
	 */
	public function getGroupsDetails(string $search = '', $limit = null, $offset = null): DataResponse {
		if ($limit !== null) {
			$limit = (int)$limit;
		}
		if ($offset !== null) {
			$offset = (int)$offset;
		}

		$groups = $this->groupManager->search($search, $limit, $offset);
		$groups = array_map(function($group) {
			/** @var IGroup $group */
			return ['id' => $group->getGID(), 'displayname' => $group->getDisplayName()];
		}, $groups);

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException	
	 *
	 * @deprecated 14 Use getGroupUsers
	 */
	public function getGroup(string $groupId): DataResponse {
		return $this->getGroup($groupId);
	}

	/**
	 * returns an array of users in the specified group
	 *
	 * @NoAdminRequired
	 *
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getGroupUsers(string $groupId): DataResponse {
		$user = $this->userSession->getUser();

		// Check the group exists
		if(!$this->groupManager->groupExists($groupId)) {
			throw new OCSException('The requested group could not be found', \OCP\API::RESPOND_NOT_FOUND);
		}

		$isSubadminOfGroup = false;
		$group = $this->groupManager->get($groupId);
		if ($group !== null) {
			$isSubadminOfGroup =$this->groupManager->getSubAdmin()->isSubAdminOfGroup($user, $group);
		}

		// Check subadmin has access to this group
		if($this->groupManager->isAdmin($user->getUID())
		   || $isSubadminOfGroup) {
			$users = $this->groupManager->get($groupId)->getUsers();
			$users =  array_map(function($user) {
				/** @var IUser $user */
				return $user->getUID();
			}, $users);
			$users = array_values($users);
			return new DataResponse(['users' => $users]);
		}

		throw new OCSException('User does not have access to specified group', \OCP\API::RESPOND_UNAUTHORISED);
	}

	/**
	 * returns an array of users details in the specified group
	 *
	 * @NoAdminRequired
	 *
	 * @param string $groupId
	 * @param int $limit
	 * @param int $offset
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getGroupUsersDetails(string $groupId, $limit = null, $offset = 0): DataResponse {
		$user = $this->userSession->getUser();

		// Check the group exists
		if(!$this->groupManager->groupExists($groupId)) {
			throw new OCSException('The requested group could not be found', \OCP\API::RESPOND_NOT_FOUND);
		}

		$isSubadminOfGroup = false;
		$group = $this->groupManager->get($groupId);
		if ($group !== null) {
			$isSubadminOfGroup =$this->groupManager->getSubAdmin()->isSubAdminOfGroup($user, $group);
		}

		// Check subadmin has access to this group
		if($this->groupManager->isAdmin($user->getUID())
		   || $isSubadminOfGroup) {
			$users = $this->groupManager->get($groupId)->getUsers();
			// Extract required number
			$users = array_slice($users, $offset, $limit);
			$users =  array_map(function($user) {
				/** @var IUser $user */
				return $this->userController->getUserData($user->getUID());
			}, $users);
			$users = array_values($users);
			return new DataResponse(['users' => $users]);
		}

		throw new OCSException('User does not have access to specified group', \OCP\API::RESPOND_UNAUTHORISED);
	}

	/**
	 * creates a new group
	 *
	 * @PasswordConfirmationRequired
	 *
	 * @param string $groupid
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function addGroup(string $groupid): DataResponse {
		// Validate name
		if(empty($groupid)) {
			$this->logger->error('Group name not supplied', ['app' => 'provisioning_api']);
			throw new OCSException('Invalid group name', 101);
		}
		// Check if it exists
		if($this->groupManager->groupExists($groupid)){
			throw new OCSException('', 102);
		}
		$this->groupManager->createGroup($groupid);
		return new DataResponse();
	}

	/**
	 * @PasswordConfirmationRequired
	 *
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function deleteGroup(string $groupId): DataResponse {
		// Check it exists
		if(!$this->groupManager->groupExists($groupId)){
			throw new OCSException('', 101);
		} else if($groupId === 'admin' || !$this->groupManager->get($groupId)->delete()){
			// Cannot delete admin group
			throw new OCSException('', 102);
		}

		return new DataResponse();
	}

	/**
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getSubAdminsOfGroup(string $groupId): DataResponse {
		// Check group exists
		$targetGroup = $this->groupManager->get($groupId);
		if($targetGroup === null) {
			throw new OCSException('Group does not exist', 101);
		}

		/** @var IUser[] $subadmins */
		$subadmins = $this->groupManager->getSubAdmin()->getGroupsSubAdmins($targetGroup);
		// New class returns IUser[] so convert back
		$uids = [];
		foreach ($subadmins as $user) {
			$uids[] = $user->getUID();
		}

		return new DataResponse($uids);
	}

}
