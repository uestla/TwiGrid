TwiGrid
=======

... is an experimental DataGrid for Nette Framework.

**Demo**: http://twigrid.1991.cz

**Demo sources**: https://github.com/uestla/twigrid-demo

It's based on another (and great) datagrid written by @hrach - https://github.com/nextras/datagrid. My datagrid is hugely inspired by this component and its programming ideas and therefore I would like to give this man a maximum credit and respect :-)


Quickstart
----------

Let's see how many steps do we have to make to create our first datagrid.

1. We'll start by creating an empty Nette Framework project:

	```composer create-project nette/sandbox twigrid-quickstart```

2. Now we'll install TwiGrid into our project:

	```composer require uestla/twigrid:*```

	We need client-side javascripts and stylesheets, so we'll copy these files:

	```
	- vendor/uestla/twigrid/client-side/twigrid.datagrid.js
	- vendor/uestla/twigrid/client-side/twigrid.datagrid.css
	- vendor/uestla/twigrid/client-side/vendors/js/nette.ajax.js
	```

	And paste them in

	```
	- www/js/twigrid.datagrid.js
	- www/js/nette.ajax.js
	- www/css/twigrid.datagrid.css
	```

	To load them, we'll update **app/templates/@layout.latte**

	*stylesheets belong to the `<head>` section:*
	```html
	<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
	<link rel="stylesheet" href="{$basePath}/css/twigrid.datagrid.css">
	```

	*and javascripts to the bottom of the page:*
	```html
	<script src="{$basePath}/js/jquery.js"></script>
	<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
	<script src="{$basePath}/js/netteForms.js"></script>
	<script src="{$basePath}/js/nette.ajax.js"></script>
	<script src="{$basePath}/js/twigrid.datagrid.js"></script>
	```

	Please note that we're loading Twitter Bootstrap client scripts as well.

3. Instead of manually creating database, we'll [download the SQLite3 file](https://github.com/uestla/twigrid-demo/raw/455d55d2e2a34bae9aaa64658bf8a4b6ddfca4a0/app/users.s3db) from the demo application and place it in **app/model/users.s3db**.

4. To tell Nette Framework that we want to use this database, we'll update **app/config/config.local.neon** file:

	```
	nette:
		database:
			dsn: 'sqlite:%appDir%/model/users.s3db'
	```

5. Now it's finally time to create our first datagrid - let's create an **app/grids/UsersGrid.php** file. We'll need database connection for data loading, so we inject it properly through constructor.

	```php
	// app/grids/UsersGrid.php

	use Nette\Http\Session;
	use Nette\Database\Context;

	class UsersGrid extends TwiGrid\DataGrid
	{
		private $database;

		function __construct(Session $session, Context $database)
		{
			parent::__construct($session);
			$this->database = $database;
		}

		protected function build()
		{
			// TODO
		}
	}
	```

	We'll define the datagrid body inside custom `build()` method. Although the table `user` has many columns, we'll have just some of them in our grid just to make it easy.

	```php
	$this->addColumn('firstname', 'Firstname');
	$this->addColumn('surname', 'Surname');
	$this->addColumn('streetaddress', 'Street address');
	$this->addColumn('city', 'City');
	$this->addColumn('country_code', 'Country');
	```

	TwiGrid also needs to know what column(s) it should consider as a primary key:

	```php
	$this->setPrimaryKey('id');
	```

	And finally we'll tell TwiGrid how to load our users:

	```php
	$this->setDataLoader(function () {
		return $this->database->table('user');
	});
	```

6. To properly inject our grid into presenters, we'll need to create a factory interface:

	```php
	// app/grids/IUsersGridFactory.php

	interface IUsersGridFactory
	{
		/** @return UsersGrid */
		function create();
	}
	```

	This interface will now be used for automatic factory generation, which is handy - we simply add this definition to **app/config/config.neon**:

	```
	services:
		- { implement: IUsersGridFactory }
	```

7. Having all of this done, we can now simply inject our grid factory into `HomepagePresenter`.

	```php
	// app/presenters/HomepagePresenter.php

	class HomepagePresenter extends BasePresenter
	{
		/** @var \IUsersGridFactory @inject */
		public $usersGridFactory;
	}
	```

	Now we'll add the control factory itself:

	```php
	// app/presenters/HomepagePresenter.php

	protected function createComponentUsersGrid()
	{
		return $this->usersGridFactory->create();
	}
	```

8. We're nearly done! Just open **app/templates/Homepage/default.latte**, delete the whole content and replace it with

	```
	{block content}
		{control usersGrid}
	{/block}
	```

	That's all, folks!

	Now when you'll open the page, you might see something like this:

	![Result screenshot](http://i.imgur.com/RHzFX1V.png)

9. Final improvement

	Maybe showing the country code isn't that sexy - we'd like to have the whole country name in "Country" column. To achieve that, we'll create custom grid template:

	```html
	{* app/grids/UsersGrid.latte *}

	{extends $defaultTemplate}

	{define body-cell-country_code}
		<td>{$record->country->title}</td>
	{/define}
	```

	And tell TwiGrid to use this template:

	```php
	// app/grids/UsersGrid.php:build()

	$this->setTemplateFile(__DIR__ . '/UsersGrid.latte');
	```

	Simple as that!

More
----

To see more examples, please visit the [demo page](http://twigrid.1991.cz/). Enjoy!
