<?php
/**
 * Scope
 *
 * @project VK
 *
 * @author  Yuri Ashurkov (rusranx)
 * @license MIT
 */

namespace VK;


class Scope
{
	const
		NOTIFY = 1 << 0,            // Пользователь разрешил отправлять ему уведомления (для flash/iframe-приложений).
		FRIENDS = 1 << 1,           // Доступ к друзьям.
		PHOTOS = 1 << 2,            // Доступ к фотографиям.
		AUDIO = 1 << 3,             // Доступ к аудиозаписям.
		VIDEO = 1 << 4,             // Доступ к видеозаписям.
		OFFERS = 1 << 5,            // Доступ к предложениям (устаревшие методы).
		QUESTIONS = 1 << 6,         // Доступ к вопросам (устаревшие методы).
		PAGES = 1 << 7,             // Доступ к wiki-страницам.
		MENU = 1 << 8,              // Добавление ссылки на приложение в меню слева.
		UNKNOWN_512 = 1 << 9,
		STATUS = 1 << 10,           // Доступ к статусу пользователя.
		NOTES = 1 << 11,            // Доступ к заметкам пользователя.
		MESSAGES = 1 << 12,         // Доступ к расширенным методам работы с сообщениями.
		WALL = 1 << 13,             // Доступ к стене
		UNKNOWN_16384 = 1 << 14,
		ADS = 1 << 15,              // Доступ к расширенным методам работы с рекламным API.
		OFFLINE = 1 << 16,          // Доступ к API в любое время со стороннего сервера (при использовании этой опции параметр expires_in, возвращаемый вместе с access_token, содержит 0 — токен бессрочный).
		DOCS = 1 << 17,             // Доступ к документам.
		GROUPS = 1 << 18,           // Доступ к группам пользователя.
		NOTIFICATIONS = 1 << 19,    // Доступ к оповещениям об ответах пользователю.
		STATS = 1 << 20,            // Доступ к статистике групп и приложений пользователя, администратором которых он является.
		UNKNOWN_2097152 = 1 << 21,
		EMAIL = 1 << 22,            // Доступ к email пользователя.
		ADSCABINET = 1 << 23,       // Доступ к кабинетам рекламной сети
		UNKNOWN_16777216 = 1 << 24,
		UNKNOWN_33554432 = 1 << 25,
		EXCHANGE = 1 << 26,         // Доступ к кабинетам биржи рекламы ВКонтакте
		MARKET = 1 << 27;           // Доступ к товарам

	/**
	 * Возвращает всемогущий Scope
	 *
	 * @return int
	 */
	public static function getAll()
	{
		$constants = (new \ReflectionClass(__CLASS__))->getConstants();
		$scope = 0;
		foreach ($constants as $constant => $value) {
			$scope |= $value;
		}

		return $scope;
	}

	private $_scope = 0;

	/**
	 * Добавление доступ к чему-либо
	 *
	 * @param array|string $permissionNames
	 * @return Scope $this
	 */
	public function addPermission($permissionNames)
	{
		if (is_array($permissionNames)) {
			foreach ($permissionNames as $permissionName)
				$this->addPermission($permissionName);
		} else {
			$permission = strtoupper(trim($permissionNames));
			if (strpos($permission, ',') !== false) {
				$permissionNames = explode(',', $permission);
				$this->addPermission($permissionNames);
			} else {
				if (defined("static::".$permission)) {
					$this->_scope |= constant("static::".$permission);
				}
			}
		}

		return $this;
	}

	public function get()
	{
		return $this->_scope;
	}

	public function __toString()
	{
		return (string)$this->_scope;
	}

}