# Akeneo PIM OroCommerce Connector

## Short overview
This extension allows you to connect [OroCommerce Enterprise](https://oroinc.com/b2b-ecommerce/) with [Akeneo PIM Enterprise](https://www.akeneo.com/) and use the latter’s rich capabilities for product information management with your OroCommerce-powered web store. Combine personalized B2B buying experience with compelling product experience to maximize your content marketing ROI and stay ahead of the B2B eCommerce game.

## Description

Akeneo is a Product Information Management (PIM) solution that provides a single place to collect, manage, and enrich your product information, create a product catalog, and distribute it to your sales and eCommerce channels. In integration with OroCommerce, Akeneo makes it faster and easier to create and deliver compelling product experiences to your online customers.

With this extension, you will be able to sync the following data from Akeneo to OroCommerce:

* Attributes and attribute options
* Attribute families and attribute family groups
* Configurable products, simple products & all product data from supported attributes
* Categories and category trees

## Compatibility

| Connector  | OroCommerce |      Akeneo      | Build |
|------------|-------------|------------------|-------|
|    v1.6    |    v1.6     | v2.3, v3.2, v4.0 | [![Build Status](https://travis-ci.org/oroinc/OroAkeneoBundle.svg?branch=1.6)](https://travis-ci.org/oroinc/OroAkeneoBundle) |
|    v3.1    |    v3.1     | v2.3, v3.2, v4.0 | [![Build Status](https://travis-ci.org/oroinc/OroAkeneoBundle.svg?branch=3.1)](https://travis-ci.org/oroinc/OroAkeneoBundle) |

## Schema

**Make sure you don't have any pending Schema Update changes or entity and migration inconsistencies:**

```
> php bin/console --env=prod doctrine:schema:update --dump-sql

[OK] Nothing to update - your database is already in sync with the current entity metadata.

```

## Dataset

Connector supports and tested on the next dataset:

* Locales: 4
* Currencies: 2
* Catalogs:
* * Levels: 4, children: 1000
* * Levels: 4, children: 1000
* Attribute Groups: 15 per Attribute Family
* Attributes: 400, 5% localizable, 2% scopable, 1% localizable and scopable, 100% usable in grid
* Attribute Families: 50, up to 100 Attributes per Attribute Family
* Products: 50000, including images

## Installation

1. To apply patches you must have the following in your composer file:
```
{
  "require": {
      "cweagans/composer-patches": "~1.6"
  },
  "extra": {
      "enable-patching": true
  }
}
```

2. Add composer package

```
composer require "oro/commerce-akeneo:3.1.*"
```

3. Follow [Setup Guide](https://doc.oroinc.com/backend/setup/upgrade-to-new-version)

4. Configure [Message Queue](https://doc.oroinc.com/backend/mq/consumer/#options)

** Recommended time limit option values is 30 seconds `--time-limit=+30seconds`

## Setting up the Integration on the Oro Side

A prefix is defined for atrribute code, attribute family code and their options if they are too long for OroCommerce. For example per default, it's `Akeneo_`. 
You can change it by setting under `config/config.yml` of your project, the followings:
```
oro_akeneo:
    code_prefix: 'ak_'
```
Important: this should be done only at really first stage, even before you set the integration and start the first sync.


Create a new integration to start synchronizing data from Akeneo to OroCommerce.

1. In OroCommerce, navigate to "System > Integrations > Manage Integrations" in the main menu and click "Create Integration".
2. Select "Akeneo" for Type to load additional integration-related fields to finish the configuration.
3. In the "General" section, specify the name for the integration (e.g., Akeneo) and the following credentials required for successful connection to Akeneo API:
   * Akeneo URL - the address of your Akeneo account.
   * UserClientId -  an identifier that authenticates your account. To create the ID, go to "System > API connections" in the Akeneo application.
   * User Secret - a key that is generated in "System > API connections" of the Akeneo application.
   * Akeneo Username - the name that the administrator will use to log into the Akeneo application.
   * Akeneo Password - the password that the admin will use to log into the Akeneo application.
4. Click "Check Akeneo Connection" once you have filled in all the settings fields. A corresponding message pops up when the connection fails/is successful.
5. If the connection is established successfully, the following fields get populated with the settings retrieved from Akeneo. In case of connection failure, the fields remain unchanged.
   * Channel - The Akeneo-specific data source. Each channel has a certain set of properties that define which data should be included in the channel. By selecting the desired channel, you request the data (products, currencies, or locales) associated with this specific channel. For example, the list of products or product descriptions in English may differ significantly from channel to channel.

      If some data of the selected channel has been changed after the initial synchronization, click "Refresh Channels" to reset the current configuration and retrieve the newly updated data from Akeneo.

   * Sync Products - The setting that defines which product type you want to synchronize. By default, we sync all products, bu you can choose to sync only the published ones by selecting the corresponding option.
   * Currency - The currency options retrieved from Akeneo. If some currency is unavailable in OroCommerce, it will not be imported. Select one or several currencies for your products from the list. In case of no currency selected, the corresponding error message pops up that will require you to choose at least one currency.

     If the currency has been updated since the initial synchronization, click "Refresh Channels" to reset the current configuration and retrieve the newly updated data from Akeneo.

   * Locales - The setting that defines how the Akeneo locales on the left will be mapped to the OroCommerce ones on the right. It is possible to set any mapping behavior, such as EN. to EN., or FR. to EN.

     **Note:** Keep in mind that you cannot map the same locale multiple times or leave the field blank. So if you do not have the appropriate Oro locale to match the Akeneo locale, you can configure or enable it following the [localization guide](https://oroinc.com/b2b-ecommerce/doc/current/admin-guide/localization) in the Oro documentation.

      If the locales have been updated since the initial synchronization, click "Refresh Channels" to reset the current configuration and retrieve the newly updated data from Akeneo.

   * Root Category - The root content node in the Oro application where you import the categories from Akeneo. You can create a specific parent category in the master catalog that would store all the categories uploaded from Akeneo. Select this category from the dropdown list.
   * Pricelist - The price list to which you will import the prices from Akeneo. This price list will help distinguish the Akeneo prices from those of other price lists provided that a product has several price options. Select the necessary pricelist from the dropdown list or create a new one directly from the integration page by clicking "+" next to the list.
   * Product Filter - The filter that enables you to embed the necessary code that would sync only the products you desire. As this filter is passed via API request, it must be filled in JSON format. More details on the format and filter options available for the products can be found in the [Filters section](https://api.akeneo.com/documentation/filter.html) of the Akeneo PIM documentation.

     **Note:** Your input is validated on the go. If you get a validation warning, ensure to correct the code or any issues reported.
   
   * Attribute Filter - The filter that enables you to limit the list of imported attributes. Values must be attribute code, separated with a semi-colon.

     **Example:** `sku;descr;price;custom`
    
     **Note:** if not defined before to save the integration, all attributes will be imported.

   * Image Attribute Filter - The filter that enables you to limit the list of imported image attributes. Values must be attribute code, separated with a semi-colon. 
   
     **Example:** `image;picture`
     
     **Note:** The filter extends Attribute Filter, no need to list attribute codes twice.

   * Merge Images From Simple Products To Configurable Product: Copy images from Simple products to their Configurable parent products.

   * Connectors - The connectors that enable you to sync either the category or products or both by selecting/deselecting the relevant connector. The attribute family connector is mandatory and cannot be disabled.
   * Default Owner - The Owner determines the list of users who can manage the integration and the data synchronized with it. All entities imported within the integration will be assigned to the selected user. By default, the field is prepopulated with the user creating the integration.

4. The Statuses field displays the log of the integration including the date and status of the connector execution, and the statistics it provides.
5. Once all the details of the integration have been specified, click "Save and Close". The integration has been successfully configured and will now appear in the integration grid.

Now you can deactivate, delete, cancel, or schedule synchronization by clicking the corresponding button in the top right corner.

### Synchronizing Data

Usually, the integration data is synchronized automatically.

To start the synchronization manually, click "Schedule Sync" on the top right. Wait for data to synchronize.

**Note:** Keep in mind that the Akeneo OroCommerce integration implements only one-way synchronization which means that the changes from Akeneo will always override the conflicts with OroCommerce.

 Click the "Check job progress" link to see the synchronization status.

**Note:** Every time you synchronize **new** product attributes from Akeneo, the `Update Schema` button appears on the top right of the Akeneo integration page and the Product Attributes page (`Products > Product Attributes`). Refresh the integration update page and click the `Update Schema` button to apply the changes and enable the product attributes. Otherwise, the attributes will be unavailable. Keep in mind that updating schema sets the Oro instance to the maintenance mode, so it is recommended to check if no critical processes are running before clicking the button.

**Note:** Click `Update Cache` button at `System > Localization > Translations` get translation applied. This action required for older versions of OroCommerce.

Once the schema update is complete, you can schedule another sync. To schedule full sync now, press the "Schedule Sync" button one more time.
## Limitations

Because of the differences between Akeneo and OroCommerce, you should take into account a few limitations.

* When you create new attributes on the Akeneo side, they won't be saved to the product information in OroCommerce until the schema has been updated manually by pressing the `Update Schema` button on the integration page.
* When you add a select or multi-select attribute in Akeneo, the options won't be synchronized until the schema has been updated.
* Akeneo date field type can have a different value for every locale. The same behavior is not possible in OroCommerce. For this reason, it is imported as a localized fallback value.
* Akeneo multi-select fields can have different values per locale. In OroCommerce, all the values from the various locales are combined.
* Akeneo select fields can have different values per locale. In OroCommerce, the value from the default locale is used.
* In OroCommerce. the Akeneo image field type is saved not as an attribute, but as a product image.
* In OroCommerce, products are validated which can cause some products to be skipped during import because the field requirements are different in Akeneo. Check the details in an integration status. For example:
* SKUs in Akeneo can contain spaces. In OroCommerce, the spaces are unacceptable. 
* An attribute family is not required in Akeneo. In OroCommerce, an attribute family is always required.
* OroCommerce does not support multiple categories, only the first category listed is used for product assignment.
* OroCommerce does not support prices per currency, only the last price per currency listed is used for product assignment.
* Categories tree rebuild starts after categories import.
* Optional listeners are disabled by default. Please reindex data and recalculate prices and slugs manually.

## Change log

### Branch 3.1-custom (diglin repository)

* FEATURE: Allow to change attribute code prefix
* ENHANCE: Catch error when image not found
* BUGFIX: attribute option code error when the code doesn't respect Oro rules
* BUGFIX: Prevent the deletion of images when an image changed on Akeneo side. Previously the connector deleted all images except the new image of product image
* BUGFIX: breaking attribute vs attribute family relation when attribute is newly imported
* FEATURE: allow to use an alternative identifier as the Akeneo one. e.g. you have in Akeneo the identifier attribute `ean` but in OroCommerce you want to use an other Akeneo attribute like 'mysku' for the SKU. So use in the configuration field of the integration, the value 'mysku'. If you want to keep the value of the Akeneo identifier, set the value `my_sku:ean`, the EAN will be kept and save into the product in OroCommerce
* FEATURE: filter the attribute list to import, import by consequence only the attributes and the product data while syncing
* FEATURE: make sure that category of an Akeneo channel is imported only instead of all channels
* FEATURE: Merge images of children products to the configurable products. Can be configured.
