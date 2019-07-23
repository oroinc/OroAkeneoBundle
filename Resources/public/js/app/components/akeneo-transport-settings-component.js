// jscs:disable validateQuoteMarks
// jscs:disable maximumLineLength
/* jshint -W109 */
define(function(require) {
    'use strict';

    var AkeneoTransportSettingsComponent;
    var $ = require('jquery');
    var _ = require('underscore');
    var LoadingMaskView = require('oroui/js/app/views/loading-mask-view');
    var BaseComponent = require('oroui/js/app/components/base/component');
    require('jquery.select2');
    var __ = require('orotranslation/js/translator');
    var tools = require('oroui/js/tools');
    var systemAccessModeOrganizationProvider = require('oroorganizationpro/js/app/tools/system-access-mode-organization-provider');

    AkeneoTransportSettingsComponent = BaseComponent.extend({
        /**
         * @property {Object}
         */
        options: {
            urlSelector: null,
            clientIdSelector: null,
            secretSelector: null,
            usernameSelector: null,
            passwordSelector: null,
            syncRoute: null,
            btnId: null,
            akeneoChannelsSelector: null,
            akeneoCurrenciesSelector: null,
            channelId: 0,
            backendUrl: null,
            akeneoLocalesList: []
        },

        /**
         * @property {jquery} url
         */
        url: null,

        /**
         * @property {jquery} clientId
         */
        clientId: null,

        /**
         * @property {jquery} secret
         */
        secret: null,

        /**
         * @property {jquery} username
         */
        username: null,

        /**
         * @property {jquery} password
         */
        password: null,

        /**
         * @property {string} syncRoute
         */
        syncRoute: null,

        /**
         * @property {string} btnId
         */
        btnId: null,

        /**
         * @property {jquery} akeneoChannelsSelector
         */
        akeneoChannels: null,

        /**
         * @property {jquery} akeneoCurrenciesSelector
         */
        akeneoCurrencies: null,

        /**
         * @property {jquery} channelId
         */
        channelId: 0,

        /**
         * @property {jquery} channelId
         */
        backendUrl: null,

        /**
         * @property {jquery} channelId
         */
        status: null,

        /**
         * @property {jquery} channelId
         */
        localesStatus: null,

        /**
         * @property {jquery} channelId
         */
        orolocalesStatus: null,

        /**
         * @property {string} synctype
         */
        synctype: 'all',

        /**
         * @property {array} akeneoLocalesList
         */
        akeneoLocalesList: [],

        /**
         * @inheritDoc
         */
        initialize: function(options) {

            this.options = _.defaults(options || {}, this.options);
            this.$elem = options._sourceElement;

            this.url = $(options.urlSelector);
            this.clientId = $(options.clientIdSelector);
            this.secret = $(options.secretSelector);
            this.username = $(options.usernameSelector);
            this.password = $(options.passwordSelector);
            this.syncRoute = options.syncRoute;
            this.btnId = options.btnId;
            this.akeneoChannels = $(options.akeneoChannelsSelector);
            this.akeneoCurrencies = $(options.akeneoCurrenciesSelector);
            this.channelId = options.channelId;
            this.backendUrl = options.backendUrl;
            this.akeneoLocalesList = options.akeneoLocalesList;

            $('#' + this.btnId).on('click', _.bind(this.onBtnClick, this));
            $('.remove-locale-item').on('click', _.bind(this.onLocaleRemoveClick, this));
            $('.add-locale-item').on('click', _.bind(this.onLocaleAddClick, this));

            $('.sync-channels-list-link').on('click', _.bind(this.onChannelsConnectionClick, this));
            $('.sync-locales-list-link').on('click', _.bind(this.onLocalesConnectionClick, this));
            $('.sync-currencies-list-link').on('click', _.bind(this.onCurrenciesConnectionClick, this));
            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[code\\]]")
                .on('change', _.bind(this.onLocaleSelect, this));

            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[locale\\]]")
                .on('change', _.bind(this.onOroLocaleSelect, this));

            $('form').on('submit', _.bind(this.onSubmitForm, this));

            var $status = this.$elem.find('.connection-status');
            var $localesStatus = this.$elem.find('.locales-status');
            var $orolocalesStatus = this.$elem.find('.orolocales-status');
            this.status = $status;
            this.localesStatus = $localesStatus;
            this.orolocalesStatus = $orolocalesStatus;
            this._localeStyleSettings();
            this.onLocaleSelect();
            this.onOroLocaleSelect();
        },

        onLocaleSelect: function() {
            var hasDuplicates = false;
            var localesStatus = this.localesStatus;
            var selectedLocalesList = [];
            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[code\\]]")
                .map(function(index, domElement) {
                    var selectorValue = $(domElement).val();
                    if ($.inArray(selectorValue, selectedLocalesList) !== -1) {
                        hasDuplicates = true;
                    } else {
                        selectedLocalesList.push($(domElement).val());
                    }
                    return $(domElement).val();
                });
            if (hasDuplicates) {
                $(localesStatus)
                    .show();
            } else {
                $(localesStatus)
                    .hide();
            }
        },

        onOroLocaleSelect: function() {
            var hasDuplicates = false;
            var orolocalesStatus = this.orolocalesStatus;
            var selectedLocalesList = [];
            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[locale\\]]")
                .map(function(index, domElement) {
                    var selectorValue = $(domElement).val();
                    if (selectorValue.length > 0) {
                        if ($.inArray(selectorValue, selectedLocalesList) !== -1) {
                            hasDuplicates = true;
                        } else {
                            selectedLocalesList.push($(domElement).val());
                        }
                    }
                    return $(domElement).val();
                });
            if (hasDuplicates) {
                $(orolocalesStatus)
                    .show();
            } else {
                $(orolocalesStatus)
                    .hide();
            }
        },

        _checkOroLocalesDuplicates: function() {
            var hasDuplicates = false;
            var selectedLocalesList = [];
            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[locale\\]]")
                .map(function(index, domElement) {
                    var selectorValue = $(domElement).val();
                    if ($.inArray(selectorValue, selectedLocalesList) !== -1) {
                        hasDuplicates = true;
                    } else {
                        selectedLocalesList.push($(domElement).val());
                    }
                    return $(domElement).val();
                });

            if (hasDuplicates) {
                return false;
            } else {
                return true;
            }
        },

        onChannelsConnectionClick: function() {
            this.synctype = 'channels';
            this.onBtnClick();
        },

        onCurrenciesConnectionClick: function() {
            this.synctype = 'currencies';
            this.onBtnClick();
        },

        onLocalesConnectionClick: function() {
            this.synctype = 'locales';
            this.onBtnClick();
        },

        onBtnClick: function() {
            var backendUrl = this.backendUrl;
            var status = this.status;
            var synctype = this.synctype;
            var self = this;
            var formData = $('form[name="oro_integration_channel_form"]').serialize();
            var organizationId = systemAccessModeOrganizationProvider.getOrganizationId();

            if (organizationId) {
                formData += '&_sa_org_id=' + organizationId;
            }

            this.loadingMaskView = new LoadingMaskView({container: $('body')});

            if ($('#check_akeneo_required_fields input').valid() === false) {
                return false;
            }
            $.ajax({
                url: backendUrl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    self.loadingMaskView.show();
                },
                success: function(json) {

                    if (json.success === false) {
                        $(status).removeClass('alert-success')
                            .show()
                            .addClass('alert-error')
                            .html(json.message);
                        return false;
                    }
                    $(status).removeClass('alert-error')
                        .show()
                        .addClass('alert-success')
                        .html(json.message);

                    switch (synctype) {
                        case 'channels':
                            self._buildAkeneoChannels(json, self);
                            break;
                        case 'currencies':
                            self._buildAkeneoCurrencies(json, self);
                            break;
                        case 'locales':
                            self._buildAkeneoLocales(json, self);
                            self._onFirstGetData();
                            break;
                        default: {
                            self._buildAkeneoChannels(json, self);
                            self._buildAkeneoLocales(json, self);
                            self._buildAkeneoCurrencies(json, self);
                            self._onFirstGetData();
                        }
                    }

                },
                complete: function() {
                    self.loadingMaskView.hide();
                    self.synctype = 'all';
                }
            });
        },

        _localeStyleSettings: function() {
            $('div.selector span', $('.locale-field')).css('width', '139px');
            $('div.selector', $('.locale-field')).css('width', '139px');
        },

        _buildCurrenciesOptions: function($key, currencyList, selected) {
            var currencySelect = '<select data-bound-input-widget="uniform" name="oro_integration_channel_form[transport][akeneoCurrencies][' + $key + '][currency]">';
            $.each(currencyList, function(keyCurrency, currency) {
                if (selected === keyCurrency) {
                    currencySelect += '<option value="' + keyCurrency + '" selected>' + currency + '</option>';
                } else {
                    currencySelect += '<option value="' + keyCurrency + '">' + currency + '</option>';
                }

            });
            currencySelect += "</select>";
            return currencySelect;
        },

        _buildAkeneoChannels: function(json, self) {
            var values = $(self.akeneoChannels).val();
            var emptyOption = $(self.akeneoChannels).find('option[value=""]').clone();
            $(self.akeneoChannels).find("option").remove();

            $(self.akeneoChannels).append(emptyOption);
            $.each(json.channels, function(key, channel) {
                if (values.indexOf(key) === -1) {
                    $(self.akeneoChannels).append("<option value='" + key + "'>" + channel + "</option>");
                } else {
                    $(self.akeneoChannels).append("<option value='" + key + "' selected>" + channel + "</option>");
                }
            });
        },

        _buildAkeneoLocales: function(json, self) {
            var values = [];
            $('#akeneoLocalesBox .control-group').each(function() {
                var key = $(this).find('input[type=hidden]').first().val();
                var value = $(this).find('select').first().val();
                values[key] = value;
            });

            var akeneoLocalesListSelect = $('#akeneoLocalesList').find('select');
            $(akeneoLocalesListSelect).find('option').remove();

            $.each(json.akeneoLocales, function(key, locale) {
                $(akeneoLocalesListSelect).append("<option value='" + locale + "'>" + key + "</option>");
            });
            self.akeneoLocalesList = json.akeneoLocales;
            this._refreshLocaleSettings(self.akeneoLocalesList);
        },

        _refreshLocaleSettings: function(akeneoLocalesList) {

            var akeneoLocalesListArray = Object.values(akeneoLocalesList);

            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[code\\]]")
                .each(function(index, element) {

                    if ($.inArray($(element).val(), akeneoLocalesListArray) === -1) {
                        var elementToRemove = $(element).parent().parent().parent();
                        $(elementToRemove).fadeOut();
                        $(elementToRemove).remove();
                    } else {
                        var optionsList = $(element).find('option');
                        var optionsListArray = [];
                        optionsList.each(function(index, option) {
                            optionsListArray.push($(option).val());
                            if ($.inArray($(option).val(), akeneoLocalesListArray) === -1) {
                                $(option).remove();
                            }
                        });

                        $.each(akeneoLocalesList, function(index, akeneoLocale) {
                            if ($.inArray(akeneoLocale, optionsListArray) === -1) {

                                $(element).append($('<option>', {
                                    value: akeneoLocale,
                                    text: index
                                }));
                            }
                        });
                    }
                });
        },

        _buildAkeneoCurrencies: function(json, self) {
            var values = $(self.akeneoCurrencies).val();
            $(self.akeneoCurrencies).find("option").remove();
            $.each(json.akeneoCurrencies, function(key, currency) {
                if (values.indexOf(key) === -1) {
                    $(self.akeneoCurrencies).append("<option value='" + key + "'>" + currency + "</option>");
                } else {
                    $(self.akeneoCurrencies).append("<option value='" + key + "' selected>" + currency + "</option>");
                }
            });
        },

        onLocaleRemoveClick: function(e) {
            $(e.currentTarget).closest('.akeneo-locale-item').fadeOut();
            $(e.currentTarget).closest('.akeneo-locale-item').remove();
            this.onLocaleSelect();
            this.onOroLocaleSelect();
        },

        onLocaleAddClick: function(e) {

            this._buildLocaleItem(this);

            var akeneoLocalesList = this.akeneoLocalesList;
            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[code\\]]")
                .each(function(index, element) {

                    if ($(element).find('option').length === 0) {
                        $.each(akeneoLocalesList, function(index, akeneoLocale) {
                            $(element).append($('<option>', {
                                value: akeneoLocale,
                                text: index
                            }));
                        });
                    }
                });

            $("#akeneoLocalesBox").find('.akeneo-box select').uniform({selectClass: 'selector input-widget-select'});
            $("#akeneoLocalesBox").find('.land-box select:not(.select2)').select2({
                containerCssClass: 'oro-select2',
                width: 139,
                dropdownCssClass: 'oro-select2__dropdown',
                placeholder: __('Please select'),
                dropdownAutoWidth: !tools.isMobile(),
                minimumInputLength: 0,
                minimumResultsForSearch: 7
            });

            $('.remove-locale-item').on('click', _.bind(this.onLocaleRemoveClick, this));
            this._localeStyleSettings();
            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[code\\]]")
                .on('change', _.bind(this.onLocaleSelect, this));
            this.onLocaleSelect();

            $("select[name ^=oro_integration_channel_form\\[transport\\]\\[akeneoLocales\\]][name $=\\[locale\\]]")
                .on('change', _.bind(this.onOroLocaleSelect, this));
            this.onOroLocaleSelect();

        },

        _buildLocaleItem: function(self) {
            $('#akeneoLocalesBox').data('index', $('#akeneoLocalesBox').find('.locale-field').length);
            var index = $('#akeneoLocalesBox').data('index') / 2;
            var key = index;

            var prototype = $('#akeneoLocalesBox').data('prototype');
            var newForm = prototype.replace(/__name__/g, key).replace(/select2/g, key);

            $('#akeneoLocalesBoxItems').append(
                newForm
            );
        },

        _onFirstGetData: function() {
            var countOptions = $('#akeneoLocalesList').find('select option').length;
            var countAddedOptions = $('.akeneo-locale-item').length;

            if (countOptions > countAddedOptions && countAddedOptions === 0) {
                this.onLocaleAddClick();
            }
        },

        onSubmitForm: function(event) {
            if (this._checkOroLocalesDuplicates() === false) {
                event.preventDefault();
                return false;
            }
        },

        dispose: function() {
            if (this.disposed) {
                return;
            }

            this.$elem.off();
            this.$elem.find(this.country).off();

            AkeneoTransportSettingsComponent.__super__.dispose.call(this);
        }
    });

    return AkeneoTransportSettingsComponent;
});
