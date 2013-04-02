Annotations
===========

If you wish to syncronize your local entities with CartoDB, you can do so
using annotations

WARNING
-------

Using this feature will increase the time needed to fetch/persist
entities, as a call to a (most likely) remote server is done on creation,
edition, deletion and loading of objects from ORM. Consider yourself warned. 


Configuration
-------------

.. code-block:: yml

   simbiotica_carto_db:
       orm:
           default:
               cartodblink: true

This enables the use of annotations. After this, you can use annotations
in your classes:

.. code-block:: php

   // app/config/config.yml
   namespace Simbiotica\AcmeBundle\Entity;
   
   use Doctrine\ORM\Mapping as ORM;
   use Simbiotica\CartoDBBundle\CartoDBLink\Mapping as CartoDB;
   
   /**
    * @ORM\Entity
    * @ORM\Table(name="project")
    * @CartoDB\CartoDBLink(connection="your_private_connection", table="projects", cascade={"all"})
    */
   
   class Project
   {
       /**
        * @ORM\Id
        * @ORM\Column(type="integer")
        * @ORM\GeneratedValue(strategy="AUTO")
        */
       protected $id;
   
       /**
        * @ORM\Column(name="name", type="string", length=128, nullable=true)
        * @CartoDB\CartoDBColumn(column="name", strong=true)
        */
       protected $name;
       
       /**
        * @ORM\Column(name="cartodb_index", type="integer", nullable=true)
        * @CartoDB\CartoDBColumn(column="index", index=true)
        */
       protected $cartodbIndex;
       
   }



Two annotations are used:

CartoDBLink
~~~~~~~~~~~

States that this class is linked to CartoDB.
- connection (required) : the name of the connection that will be used to connect to
CartoDB, as specified in you configuration files
- table (required) : the name of the CartoDB table to which this entity will be mapped
- cascade (optional, can have "fetch", "persist", "remove" and "all", default: fetch + persist):
when will syncing occur. By default, only propagates changes on fetch, insert and update.

CartoDBColumn
~~~~~~~~~~~~~

States which columns will be synced
- column (required) : the name of the column in CartoDB table to which this
field will be mapped
- index (optional, default: false, one per entity is needed) : if true, this
field will be used locally to store cartodb_id, and remotely to store the
local id. Unless you have a really good reason to do so, you should avoid
manipulating this field yourself.
- strong(optional, default: false): if set to true, when loading the object,
the value received from CartoDB for this field will override the one available
on the current database. The value stored in the local database will only be
overwritten if you save the changes afterwards. 


Some features you might miss
----------------------------

Some of them will be implemented in a near future, others in a not-so-near
future, others in your future (aka submit a PR with them):
- Syncing only works for fields managed by Doctrine. Syncing for non-doctrine
managed fields is high up on my TODO list
- Unidirectional references, for when you can't / don't want to change one
of the DBs.
- XML and YAML class configuration
- (some other stuff)
