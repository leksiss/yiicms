<?php

/**
 * This is the model class for table "{{news}}".
 *
 * The followings are the available columns in table '{{news}}':
 * @property integer $id
 * @property string $creation_date
 * @property string $change_date
 * @property string $date
 * @property string $title
 * @property string $slug
 * @property string $body_cut
 * @property string $body
 * @property string $image
 * @property string $user_id
 * @property integer $status
 * @property integer $is_protected
 * @property string $keywords
 * @property string $description
 *
 * The followings are the available model relations:
 * @property User $user
 */
class News extends CActiveRecord
{
	public $author_search;
	const STATUS_DRAFT      = 0;
	const STATUS_PUBLISHED  = 1;
	const STATUS_MODERATION = 2;

	const PROTECTED_NO  = 0;
	const PROTECTED_YES = 1;

	/** @var string Extensions for gallery images */
	public $newsExt = 'jpg';
	/** @var string directory in web root for galleries */
	public $newsDir = 'uploads/news';

	public $versions = array(
		'thumb' => array(
			'resize' => array(130, null),
		),
		'small' => array(
			'resize' => array(320, null),
		),
		'standard' => array(
			'resize' => array(640, null),
		),
		'medium' => array(
			'resize' => array(1024, null),
		),
	);
	/** @var int Maximum Photo width */
	public $maxWidth = 1024;
	/** @var int Maximum Photo height */
	public $maxHeight = 768;

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return News the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return '{{news}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array('date, title, body_cut', 'required', 'on' => array('update', 'insert')),
			array('status, is_protected', 'numerical', 'integerOnly' => true),
			array('status', 'in', 'range' => array_keys($this->getStatusList())),
			array('title, slug, keywords', 'length', 'max' => 150),
			array('slug', 'unique'),
			array('body_cut', 'length', 'max' => 400),
			array('description', 'length', 'max' => 250),
			array('image', 'length', 'max' => 300),
			array('image', 'file', 'types'=> 'jpg,gif,bmp', 'allowEmpty'=> true, 'safe'=> false),

			array('title, slug, body_cut, body, keywords, description', 'filter', 'filter' => 'trim'),
			array(
				'title, slug, keywords, description',
				'filter',
				'filter' => array($obj = new CHtmlPurifier(), 'purify')
			),
			array(
				'slug',
				'match',
				'pattern' => '/^[a-zA-Z0-9_\-]+$/',
				'message' => Yii::t('news', 'Строка содержит запрещенные символы: {attribute}')
			),

			array(
				'id, creation_date, change_date, date, title, slug, body_cut, body, user_id, status, is_protected, keywords, description,   author_search',
				'safe',
				'on'=> 'search'
			),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'author' => array(self::BELONGS_TO, 'User', 'user_id'),
		);
	}

	/**
	 * @return array
	 */
	public function scopes()
	{
		return array(
			'published' => array(
				'condition' => 'status = :status',
				'params' => array(':status' => self::STATUS_PUBLISHED)
			),
			'protected' => array(
				'condition' => 'is_protected = :is_protected',
				'params' => array(':is_protected' => self::PROTECTED_YES)
			),
			'public' => array(
				'condition' => 'is_protected = :is_protected',
				'params'  => array(':is_protected' =>  self::PROTECTED_NO)
			),
			'recent' => array(
				'order' => 'creation_date DESC',
				'limit' => 5,
			),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id'            => 'ID',
			'creation_date' => Yii::t('news', 'Создано'),
			'change_date'   => Yii::t('news', 'Изменено'),
			'date'          => Yii::t('news', 'Дата'),
			'title'         => Yii::t('news', 'Заголовок'),
			'slug'          => Yii::t('news', 'Ссылка'),
			'body_cut'      => Yii::t('news', 'Превью'),
			'body'          => Yii::t('news', 'Текст'),
			'image'         => Yii::t('news', 'Изображение'),
			#'user_id' 		=> Yii::t('news', 'Пользователь',
			'status'        => Yii::t('news', 'Статус'),
			'is_protected'  => Yii::t('news', 'Доступ только для авторизованных пользователей'),
			'keywords'      => Yii::t('news', 'Ключевые слова (Seo)'),
			'description'   => Yii::t('news', 'Описание (Seo)'),

			'author_search' => Yii::t('page', 'Автор'),
		);
	}

	public function beforeValidate()
	{
		if ( !$this->slug )
		{
			$this->slug = Text::translit($this->title);
		}
		return parent::beforeValidate();
	}

	public function beforeSave()
	{
		$this->change_date = new CDbExpression('NOW()');
		$this->date        = date('Y-m-d', strtotime($this->date));

		if ( $imageFile = CUploadedFile::getInstance($this, 'image') )
		{
			if ( !$this->isNewRecord )
			{
				$this->deleteImage(); // удаляем старое изображение, если обновляем новость
			}
			$this->image = pathinfo($imageFile->getName(), PATHINFO_FILENAME) . '.jpg';
			$this->setImage($imageFile->getTempName());
		}

		if ( $this->isNewRecord )
		{
			$this->creation_date = $this->change_date;
			$this->user_id       = Yii::app()->user->getId();
		}

		return parent::beforeSave();
	}

	public function beforeDelete()
	{
		if ( parent::beforeDelete() )
		{
			$this->deleteImage(); // удалили модель? удаляем и файл
			return true;
		}
		return false;
	}

	public function afterFind()
	{
		parent::afterFind();

		$this->date = date('d.m.Y', strtotime($this->date));
	}

	private function setImage($path)
	{
		/** @var $image Image */
		$image = Yii::app()->image->load($path);

		/** resize image to Max width x height */
		$image->cresize($this->maxWidth, $this->maxHeight);
		$image->save($this->getUploadPath() . '/' . $this->getFileName('') . '.jpg');

		/** resize images to user versions and put-sort it to versions-named folders */
		foreach ($this->versions as $version => $actions)
		{
			$image = Yii::app()->image->load($path);
			foreach ($actions as $method => $args)
			{
				# if it width >= version->width image no need to resize
				if ( $image->width >= $args['0'] )
				{
					if ( !is_dir($this->getUploadPath() . '/' . $version) )
					{
						mkdir($this->getUploadPath() . '/' . $version);
					}
					call_user_func_array(array($image, $method), $args);
					$image->save($this->getUploadPath() . '/' . $version . '/' . $this->getFileName('') . '.jpg');
				}
			}
		}
	}

	private function deleteImage()
	{
		$this->removeFile($this->getUploadPath() . '/' . $this->getFileName('') . '.jpg');

		foreach ($this->versions as $version => $actions) {
			$this->removeFile($this->getUploadPath() . '/' . $version . '/' . $this->getFileName('') . '.jpg');
			$this->removeDir($this->getUploadPath() . '/' . $version);
		}
		$this->removeDir($this->getUploadPath());
	}

	public function renamePath($newPathName)
	{
		if ( is_dir($this->getUploadPath()) )
	 		rename($this->getUploadPath(), $this->newsDir . '/' . $newPathName);
	}

	private function removeFile($fileName)
	{
		if ( file_exists($fileName) )
		{
			@unlink($fileName);
		}
	}

	private function removeDir($dirName)
	{
		if ( is_dir($dirName) )
		{
			rmdir($dirName);
		}
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria = new CDbCriteria;
		$criteria->with = array('author');

		$criteria->compare('id', $this->id);
		$criteria->compare('creation_date', $this->creation_date,true);
		$criteria->compare('change_date', $this->change_date,true);
		$criteria->compare('date', $this->date,true);
		$criteria->compare('title', $this->title,true);
		$criteria->compare('slug', $this->slug,true);
		$criteria->compare('body_cut', $this->body_cut,true);
		$criteria->compare('body', $this->body,true);
		$criteria->compare('author.username', $this->author_search, true);#$criteria->compare('user_id', $this->user_id,true);
		#$criteria->compare('author.firstname', $this->author_search, true);
		if ( $this->status != '' )
		{
			$criteria->compare('status', $this->status);
		}
		$criteria->compare('is_protected', $this->is_protected);
		$criteria->compare('keywords', $this->keywords,true);
		$criteria->compare('description', $this->description,true);

		$sort = new CSort;
		$sort->defaultOrder = 't.date DESC';
		$sort->attributes = array(
			'author_search' => array(
				'asc' => 'author.username',
				'desc'=> 'author.username DESC',
			),
			'*',
		);
		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
			'sort'	   => $sort
		));
	}

	/**
	 * @return string link to news
	 */
	public function getPermanentLink()
	{
		return Yii::app()->createUrl('/news/show/', array('title' => $this->slug));
	}

	public function getStatusList()
	{
		return array(
			self::STATUS_DRAFT      => Yii::t('news', 'Черновик'),
			self::STATUS_PUBLISHED  => Yii::t('news', 'Опубликовано'),
			self::STATUS_MODERATION => Yii::t('news', 'На модерации')
		);
	}

	public function getStatus()
	{
		$data = $this->getStatusList();
		return array_key_exists($this->status, $data) ? $data[$this->status] : Yii::t('news', 'неизвестно');
	}


	public function getProtectedStatusList()
	{
		return array(
			self::PROTECTED_NO	=> Yii::t('news', 'нет'),
			self::PROTECTED_YES => Yii::t('news', 'да')
		);
	}

	public function getProtectedStatus()
	{
		$data = $this->getProtectedStatusList();
		return array_key_exists($this->is_protected, $data) ? $data[$this->is_protected] : Yii::t('news', 'неизвестно');
	}

	private function getUploadPath()
	{
		if ( !is_dir($this->newsDir . '/' . $this->slug) )
		{
			mkdir($this->newsDir . '/' . $this->slug, 0777);
		}
		return $this->newsDir . '/' . $this->slug;
	}

	private function getFileName()
	{
		return pathinfo($this->image, PATHINFO_FILENAME);
	}

	/**
	 * @return bool|string image URL
	 */
	public function getImageUrl()
	{
		if ( $this->image )
		{
			return Yii::app()->baseUrl . '/uploads/news/' . $this->image;
		}
		return false;
	}
}