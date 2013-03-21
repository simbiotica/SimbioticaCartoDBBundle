Configuration
=============

SimbioticaCartoDBBundle supports multiple connections to CartoDB, for both public and private tables

.. code-block:: yaml
# app/config/config.yml
# CartoDB configuration
simbiotica_carto_db:
    connections:
        #create simbiotica.cartodb_connection.your_public_connection service
        your_public_connection: 
            private: false
            subdomain: 
        #create simbiotica.cartodb_connection.your_private_connection service
        your_private_connection:
            private: true
            key: 
            secret: 
            subdomain: 
            email: 
            password: 
        (add more connections if you wish)


All fields are required. For each connection, a service is created.



