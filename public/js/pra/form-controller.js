(function (window, document) {
    const INSTRUMENT_TYPE_OPTIONS = ['Assignment', 'Surrender', 'Subdivision', 'Merger', 'Withdrawn', 'Revoke'];

    let cachedInstrumentTypes = null;
    let instrumentTypeRequest = null;

    async function requestInstrumentTypes(url) {
        if (!url) {
            return [];
        }

        if (cachedInstrumentTypes) {
            return cachedInstrumentTypes;
        }

        if (!instrumentTypeRequest) {
            instrumentTypeRequest = fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Instrument type lookup failed (${response.status})`);
                    }
                    return response.json();
                })
                .then((payload) => {
                    const list = Array.isArray(payload)
                        ? payload
                        : Array.isArray(payload?.data)
                            ? payload.data
                            : [];
                    cachedInstrumentTypes = list;
                    return list;
                })
                .catch((error) => {
                    console.warn('Failed to retrieve instrument types', error);
                    throw error;
                })
                .finally(() => {
                    instrumentTypeRequest = null;
                });
        }

        return instrumentTypeRequest;
    }

    const INSTRUMENT_PARTY_MAP = {
        Assignment: { from: 'Assignor', to: 'Assignee' },
        Surrender: { from: 'Surrenderor', to: 'Surrenderee (Kano State Govt.)' },
        Subdivision: { from: 'Parent Title Holder', to: 'Subdivided Owner(s)' },
        Merger: { from: 'Merging Owner', to: 'Resultant Owner' },
        Withdrawn: { from: 'Kano State Govt.', to: 'Original Owner' },
        Revoke: { from: 'Kano State Govt.', to: 'Original Owner' }
    };

    const INSTRUMENT_COLOR_MAP = {
        Assignment: { border: 'border-blue-200', bg: 'bg-blue-50', tag: 'bg-blue-100 text-blue-900' },
        Surrender: { border: 'border-amber-200', bg: 'bg-amber-50', tag: 'bg-amber-100 text-amber-900' },
        Subdivision: { border: 'border-emerald-200', bg: 'bg-emerald-50', tag: 'bg-emerald-100 text-emerald-900' },
        Merger: { border: 'border-purple-200', bg: 'bg-purple-50', tag: 'bg-purple-100 text-purple-900' },
        Withdrawn: { border: 'border-rose-200', bg: 'bg-rose-50', tag: 'bg-rose-100 text-rose-900' },
        Revoke: { border: 'border-red-200', bg: 'bg-red-50', tag: 'bg-red-100 text-red-900' },
        default: { border: 'border-gray-200', bg: 'bg-gray-50', tag: 'bg-gray-100 text-gray-800' }
    };

    const TRANSACTION_PARTY_LABELS = {
        'Power of Attorney': { first: 'Grantor', second: 'Grantee' },
        'Deed of Assignment': { first: 'Assignor', second: 'Assignee' },
        'ST Assignment': { first: 'Assignor', second: 'Assignee' },
        'Deed of Mortgage': { first: 'Mortgagor', second: 'Mortgagee', third: 'Co-mortgagor', fourth: 'Fourth Party' },
        'Tripartite Mortgage': { first: 'Mortgagor', second: 'Mortgagee', third: 'Co-mortgagor', fourth: 'Fourth Party' },
        'Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'ST Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'SLTR Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Customary Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Deed of Transfer': { first: 'Transferor', second: 'Transferee' },
        'Deed of Gift': { first: 'Donor', second: 'Donee' },
        'Deed of Lease': { first: 'Lessor', second: 'Lessee' },
        'Deed of Sub Lease': { first: 'Lessor', second: 'Lessee' },
        'Deed of Sub Under Lease': { first: 'Lessor', second: 'Lessee' },
        'Indenture of Lease': { first: 'Lessor', second: 'Lessee' },
        'Quarry Lease': { first: 'Lessor', second: 'Lessee' },
        'Private Lease': { first: 'Lessor', second: 'Lessee' },
        'Building Lease': { first: 'Lessor', second: 'Lessee' },
        'Tenancy Agreement': { first: 'Landlord', second: 'Tenant' },
        'Deed of Surrender and Release': { first: 'Surrenderor', second: 'Surrenderee', third: 'Co-Surrenderor' },
        'Letter of Administration': { first: 'Administrator', second: 'Beneficiary' },
        'Certificate of Purchase': { first: 'Vendor', second: 'Purchaser' }
    };

    const FIRST_PARTY_FIELD_MAP = {
        'Deed of Assignment': 'Assignor',
        'ST Assignment': 'Assignor',
        'Deed of Mortgage': 'Mortgagor',
        'Tripartite Mortgage': 'Mortgagor',
        'Deed of Surrender and Release': 'Surrenderor',
        'Deed of Sub Lease': 'Lessor',
        'Deed of Sub Under Lease': 'Lessor',
        'Indenture of Lease': 'Lessor',
        'Quarry Lease': 'Lessor',
        'Private Lease': 'Lessor',
        'Building Lease': 'Lessor',
        'Tenancy Agreement': 'Landlord',
        'Deed of Transfer': 'Transferor',
        'Deed of Gift': 'Donor',
        'Letter of Administration': 'Administrator',
        'Certificate of Purchase': 'Vendor'
    };

    const SECOND_PARTY_FIELD_MAP = {
        'Deed of Assignment': 'Assignee',
        'ST Assignment': 'Assignee',
        'Deed of Mortgage': 'Mortgagee',
        'Tripartite Mortgage': 'Mortgagee',
        'Deed of Surrender and Release': 'Surrenderee',
        'Deed of Sub Lease': 'Lessee',
        'Deed of Sub Under Lease': 'Lessee',
        'Indenture of Lease': 'Lessee',
        'Quarry Lease': 'Lessee',
        'Private Lease': 'Lessee',
        'Building Lease': 'Lessee',
        'Tenancy Agreement': 'Tenant',
        'Deed of Transfer': 'Transferee',
        'Deed of Gift': 'Donee',
        'Letter of Administration': 'Beneficiary',
        'Certificate of Purchase': 'Purchaser'
    };

    const THIRD_PARTY_FIELD_MAP = {
        'Deed of Mortgage': 'Mortgagor_2',
        'Tripartite Mortgage': 'Mortgagor_2',
        'Deed of Surrender and Release': 'Surrenderor_2'
    };

    const GOVERNMENT_TRANSACTION_TYPES = [
        'Certificate of Occupancy',
        'ST Certificate of Occupancy',
        'SLTR Certificate of Occupancy',
        'Customary Right of Occupancy',
        'Right of Occupancy',
        'Occupation Permit',
        'Occupancy Permit',
        'Occupancy Permit (OP)',
        'OP'
    ];

    const ALL_COLOR_CLASSES = Array.from(
        new Set(
            Object.values(INSTRUMENT_COLOR_MAP).flatMap((colors) => {
                const tagClasses = typeof colors.tag === 'string' ? colors.tag.split(' ') : [];
                return [colors.border, colors.bg, ...tagClasses];
            })
        )
    );

    function normalizeText(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).trim();
    }

    class PraFormController {
        constructor(container) {
            this.container = container;
            this.form = container.querySelector('#property-record-form');
            this.dialog = document.getElementById('property-form-dialog');
            this.modelElements = new Map();
            this.optionalBadges = Array.from(container.querySelectorAll('[data-visible-when="property"]'));
            this.indexModeElements = Array.from(container.querySelectorAll('[data-index-class]'));
            this.transactionDetailsSection = container.querySelector('[data-section="transaction-details"]');
            this.partyLabelFirst = container.querySelector('[data-role="party-label-first"]');
            this.partyLabelSecond = container.querySelector('[data-role="party-label-second"]');
            this.partyLabelThird = container.querySelector('[data-role="party-label-third"]');
            this.partyLabelFourth = container.querySelector('[data-role="party-label-fourth"]');
            this.partyInputFirst = container.querySelector('[data-name-key="firstParty"]');
            this.partyInputSecond = container.querySelector('[data-name-key="secondParty"]');
            this.partyInputThird = container.querySelector('[data-name-key="thirdParty"]');
            this.partyInputFourth = container.querySelector('[data-name-key="fourthParty"]');
            this.partyThirdWrapper = container.querySelector('[data-role="party-third-wrapper"]');
            this.partyFourthWrapper = container.querySelector('[data-role="party-fourth-wrapper"]');
            this.tripartiteToggleWrapper = container.querySelector('[data-role="tripartite-toggle"]');
            this.fourthPartyToggleWrapper = container.querySelector('[data-role="fourth-party-toggle"]');
            this.tripartiteToggleLabelText = container.querySelector('[data-role="tripartite-toggle-label"]');
            this.instrumentEmptyMessage = container.querySelector('[data-role="instrument-empty"]');
            this.instrumentList = container.querySelector('[data-role="instrument-list"]');
            this.instrumentTemplate = container.querySelector('#pra-instrument-entry-template');
            this.fileNumberMessage = container.querySelector('[data-role="no-file-number-copy"]');
            this.matchingRecordsContainer = container.querySelector('[data-role="matching-records-container"]');
            this.matchingRecordsResults = container.querySelector('[data-role="matching-records-results"]');
            this.smartWrapper = container.querySelector('[data-role="smart-wrapper"]');
            this.tempStatus = document.getElementById('modal-temp-status');
            this.tempValue = document.getElementById('modal-temp-value');
            this.tempError = document.getElementById('modal-temp-error');
            this.toggleTempCheckbox = document.getElementById('modal-no-file-number-toggle');
            this.refreshTempButton = document.getElementById('modal-refresh-temp');
            this.tempWrapper = document.getElementById('modal-temp-wrapper');
            this.modalMlsMethodToggle = document.getElementById('modal-mls-method-toggle');
            this.modalMlsInputSections = Array.from(document.querySelectorAll('.mls-input-section'));
            this.tempEndpoint = container.dataset.tempEndpoint || null;
            this.instrumentTypesUrl = container.dataset.instrumentTypesUrl || '';
            this.descriptionTextarea = container.querySelector('[data-model="propertyDescription"]');
            this.regPreviewBox = container.querySelector('[data-role="reg-preview"]');
            this.regPreviewValue = container.querySelector('[data-role="reg-preview-value"]');
            this.streetComponent = container.querySelector('[data-component="street-name"]');
            this.districtComponent = container.querySelector('[data-component="district-name"]');
            this.refreshDescriptionButton = container.querySelector('[data-action="refresh-description"]');
            this.addInstrumentButton = container.querySelector('[data-action="add-instrument"]');
            this.serialSynchronizers = Array.from(container.querySelectorAll('[data-sync]'));
            this.instrumentEntries = [];
            this.initialising = true;
            this.submitButton = container.querySelector('#property-submit-btn');
            this.submitButtonDefaultLabel = this.submitButton ? (this.submitButton.dataset.defaultLabel || this.submitButton.textContent.trim()) : 'Submit';
            this.updateBanner = container.querySelector('[data-role="update-banner"]');
            this.updateBannerLabel = container.querySelector('[data-role="update-label"]');
            this.formActionOriginal = this.form ? this.form.getAttribute('action') : '';
            this.updateBaseUrl = this.form ? (this.form.dataset.updateBase || '') : '';
            this.methodOverrideInput = this.form ? this.form.querySelector('input[name="_method"]') : null;
            this.propertyIdInput = this.form ? this.form.querySelector('#property_id') : null;
            this.formActionInput = this.form ? this.form.querySelector('#form_action') : null;
            this.csrfToken = this.resolveCsrfToken();
            this.isSubmitting = false;
            this.pendingUpdatePrompt = false;
            this.instrumentTypeOptions = [];
            this.transactionTypeSelects = Array.from(container.querySelectorAll('select[data-role="transaction-type"]'));
            this.transactionTypeSelects.forEach((select) => {
                if (!select.dataset.placeholder) {
                    select.dataset.placeholder = select.dataset.placeholder || 'Select type';
                }
                if (!select.dataset.initialValue) {
                    select.dataset.initialValue = select.value || '';
                }
            });
            this.opFieldsWrapper = container.querySelector('[data-role="op-fields-wrapper"]');
            this.cofoFieldsWrapper = container.querySelector('[data-role="cofo-fields-wrapper"]');
            this.state = {
                formMode: 'property',
                transactionType: '',
                firstParty: '',
                secondParty: '',
                thirdParty: '',
                fourthParty: '',
                party1: '',
                party2: '',
                party3: '',
                party4: '',
                serialNo: '',
                pageNo: '',
                volumeNo: '',
                landUse: '',
                propertyDescription: '',
                houseNo: '',
                plotNo: '',
                tpNo: '',
                lpknNo: '',
                approvedPlanNo: '',
                plotSize: '',
                scale: '',
                dateRecommended: '',
                dateApproved: '',
                leaseBegins: '',
                leaseExpires: '',
                metricSheet: '',
                streetName: '',
                currentStreetName: '',
                district: '',
                currentDistrict: '',
                lga: '',
                state: '',
                fileNumber: '',
                isMapped: false,
                hasOfficialFileNumber: true,
                showManualEntry: false,
                transactionDate: '',
                regDate: '',
                regTime: '',
                deedsDate: '',
                deedsTime: '',
                opType: '',
                opSerialNumber: '',
                cofoType: '',
                tempFileNumber: '',
                tempFileNumberLoading: false,
                tempFileNumberError: '',
                period: '',
                periodUnit: 'Years',
                tripartiteHasThird: false,
                isUpdateMode: false,
                updateRecordId: null
            };
            this.publicApi = this.buildPublicApi();
            container._x_dataStack = [this.publicApi];
        }

        buildPublicApi() {
            const api = {};
            const exposedKeys = [
                'formMode',
                'transactionType',
                'firstParty',
                'secondParty',
                'thirdParty',
                'fourthParty',
                'serialNo',
                'pageNo',
                'volumeNo',
                'landUse',
                'propertyDescription',
                'houseNo',
                'plotNo',
                'tpNo',
                'lpknNo',
                'approvedPlanNo',
                'plotSize',
                'scale',
                'dateRecommended',
                'dateApproved',
                'leaseBegins',
                'leaseExpires',
                'metricSheet',
                'streetName',
                'currentStreetName',
                'district',
                'currentDistrict',
                'lga',
                'state',
                'fileNumber',
                'hasOfficialFileNumber',
                'showManualEntry',
                'transactionDate',
                'regDate',
                'regTime',
                'deedsDate',
                'deedsTime',
                'opType',
                'opSerialNumber',
                'cofoType',
                'tempFileNumber',
                'tempFileNumberLoading',
                'tempFileNumberError',
                'period',
                'periodUnit',
                'tripartiteHasThird',
                'isMapped',
                'isUpdateMode',
                'updateRecordId',
                'party1',
                'party2',
                'party3',
                'party4',
                'hasFourthParty'
            ];

            exposedKeys.forEach((key) => {
                Object.defineProperty(api, key, {
                    enumerable: true,
                    get: () => this.state[key],
                    set: (value) => this.setState(key, value)
                });
            });

            Object.defineProperty(api, 'instrumentEntries', {
                enumerable: true,
                get: () => this.instrumentEntries
            });

            api.$nextTick = (callback) => {
                if (typeof callback === 'function') {
                    window.requestAnimationFrame(() => callback());
                }
            };

            api.updatePropertyDescription = () => this.updatePropertyDescription();
            api.addInstrumentEntry = () => this.addInstrumentEntry();
            api.removeInstrumentEntry = (index) => this.removeInstrumentEntry(index);
            api.requestTempFileNumber = (force) => this.fetchTempFileNumber(Boolean(force));
            api.resetUpdateMode = () => this.exitUpdateMode();
            api.resetForm = () => this.resetForm();

            return api;
        }

        init() {
            this.bindModelElements();
            if (!this.state.formMode) {
                this.state.formMode = 'property';
            }
            this.bindRefreshButton();
            this.bindStreetComponent();
            this.bindDistrictComponent();
            this.bindFileNumberControls();
            this.bindPropertyDescriptionRefresh();
            this.bindInstrumentControls();
            this.bindRegNumberSynchronisers();
            this.bindSmartSelectorWatcher();
            this.bindUpdateModeHandlers();
            if (this.state.transactionType === 'Tripartite Mortgage' && normalizeText(this.state.thirdParty).length > 0) {
                this.state.tripartiteHasThird = true;
                const tripartiteCheckbox = this.modelElements.get('tripartiteHasThird');
                if (tripartiteCheckbox) {
                    tripartiteCheckbox.checked = true;
                }
                this.syncModelElement('tripartiteHasThird', true);
            }
            this.initialising = false;
            this.syncAllState();
            this.updateTransactionContext();
            this.updatePropertyDescription();
            this.updateRegPreview();
            this.syncFileNumberSections();
            this.updateTempStatus();
            this.loadInstrumentTypeOptions();
        }

        bindModelElements() {
            const elements = this.container.querySelectorAll('[data-model]');
            elements.forEach((element) => {
                const modelKey = element.dataset.model;
                if (!modelKey) {
                    return;
                }

                if (!element.dataset.defaultClass) {
                    element.dataset.defaultClass = element.className || '';
                }

                if (!element.dataset.placeholderDefault) {
                    element.dataset.placeholderDefault = element.getAttribute('placeholder') || '';
                }

                this.modelElements.set(modelKey, element);

                const initialValue = this.extractElementValue(element, modelKey);
                this.state[modelKey] = initialValue;

                const eventName = element.tagName === 'SELECT' ? 'change' : 'input';
                element.addEventListener(eventName, () => {
                    const nextValue = this.extractElementValue(element, modelKey);
                    this.setState(modelKey, nextValue, { source: 'element' });
                });
            });
        }

        extractElementValue(element, key) {
            if (!element) {
                return '';
            }

            if (element.type === 'checkbox') {
                return element.checked;
            }

            if (key === 'hasOfficialFileNumber') {
                return element.value === '1' || element.value === 'true';
            }

            return element.value || '';
        }

        setState(key, value, options = {}) {
            if (key === 'hasOfficialFileNumber') {
                value = Boolean(value);
            }

            if (this.state[key] === value) {
                return;
            }

            this.state[key] = value;

            if (this.initialising || options.silent) {
                return;
            }

            this.onStateChange(key, value, options);
        }

        onStateChange(key, value, options = {}) {
            switch (key) {
                case 'formMode':
                    this.applyFormMode(value);
                    break;
                case 'transactionType':
                    this.syncModelElement(key, value);
                    this.syncTransactionTypeSelects(value);
                    this.updateTransactionContext();
                    // When Surrender and Release is selected, re-check mortgages for current file number
                    if (String(value).toLowerCase() === 'deed of surrender and release') {
                        const fileno = normalizeText(this.state.fileNumber || this.state.fileno || '');
                        if (fileno.length > 0) {
                            this.checkFilenoForMortgages(fileno);
                        }
                    } else {
                        // clear mapping when not a surrender/release
                        this.setState('isMapped', false);
                    }
                    break;
                case 'fileNumber':
                    // Always check for mortgages/surrender records when file number changes
                    // pass true to enable auto-selection of transaction type
                    const fileno = normalizeText(value);
                    if (fileno.length > 0) {
                        this.fetchAndDisplayMatchingRecords(fileno);
                        // Backfill the shared property details (location, plot,
                        // district, land use, etc.) from any existing record on
                        // this file so selecting a file number prefills the form
                        // here just like it does on the instrument-capture page.
                        // Skip in update mode so a loaded record is not clobbered,
                        // and skip the silent re-dispatch used while populating.
                        if (!this.state.isUpdateMode && !options.silent) {
                            this.autoBackfillPropertyDetailsFromFile(fileno);
                        }
                    } else {
                        if (this.matchingRecordsContainer) {
                            this.matchingRecordsContainer.classList.add('hidden');
                        }
                    }
                    this.syncModelElement(key, value);
                    break;
                case 'tripartiteHasThird':
                    this.syncModelElement(key, value);
                    this.updateTripartiteControls();
                    break;
                case 'isMapped':
                    // Ensure the hidden input carries explicit 1/0 strings for server validation
                    this.syncModelElement(key, value ? '1' : '0');
                    break;
                case 'firstParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party1', value);
                    this.state.party1 = value;
                    break;
                case 'secondParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party2', value);
                    this.state.party2 = value;
                    break;
                case 'thirdParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party3', value);
                    this.state.party3 = value;
                    break;
                case 'fourthParty':
                    this.syncModelElement(key, value);
                    this.syncModelElement('party4', value);
                    this.state.party4 = value;
                    break;
                case 'hasFourthParty':
                    this.syncModelElement(key, value);
                    this.updateTripartiteControls();
                    break;
                case 'serialNo':
                case 'pageNo':
                case 'volumeNo':
                case 'landUse':
                case 'houseNo':
                case 'plotNo':
                case 'tpNo':
                case 'lpknNo':
                case 'approvedPlanNo':
                case 'plotSize':
                case 'scale':
                case 'dateRecommended':
                case 'dateApproved':
                case 'leaseBegins':
                case 'leaseExpires':
                case 'metricSheet':
                case 'streetName':
                case 'currentStreetName':
                case 'district':
                case 'currentDistrict':
                case 'lga':
                case 'state':
                case 'transactionDate':
                case 'regDate':
                case 'regTime':
                case 'deedsDate':
                case 'deedsTime':
                case 'opType':
                case 'opSerialNumber':
                case 'period':
                case 'periodUnit':
                    this.syncModelElement(key, value);
                    if (['houseNo', 'currentStreetName', 'currentDistrict', 'lga', 'state'].includes(key)) {
                        this.updatePropertyDescription();
                    }
                    if (['serialNo', 'pageNo', 'volumeNo'].includes(key)) {
                        this.updateRegPreview();
                    }
                    break;
                case 'propertyDescription':
                    this.syncModelElement(key, value);
                    break;
                case 'fileNumber':
                    this.syncModelElement(key, value);
                    break;
                case 'hasOfficialFileNumber':
                    this.syncHasOfficialField(value);
                    this.syncFileNumberSections();
                    break;
                case 'tempFileNumber':
                case 'tempFileNumberLoading':
                case 'tempFileNumberError':
                    this.updateTempStatus();
                    if (window.GlobalFileNoModal && typeof window.GlobalFileNoModal.updatePreview === 'function') {
                        window.GlobalFileNoModal.updatePreview();
                    }
                    break;
                default:
                    this.syncModelElement(key, value);
                    break;
            }
        }

        syncTransactionTypeSelects(value) {
            const normalized = value || '';
            this.transactionTypeSelects.forEach((select) => {
                if (select.value !== normalized) {
                    select.value = normalized;
                }
            });
        }

        async loadInstrumentTypeOptions() {
            if (!this.instrumentTypesUrl) {
                return;
            }

            try {
                const options = await requestInstrumentTypes(this.instrumentTypesUrl);
                if (!Array.isArray(options) || options.length === 0) {
                    return;
                }
                this.instrumentTypeOptions = options;
                this.populateTransactionTypeSelects();
            } catch (error) {
                const selects = this.transactionTypeSelects;
                if (selects && selects.length) {
                    selects.forEach((select) => {
                        if (!select.dataset.errorNotified) {
                            select.dataset.errorNotified = 'true';
                            select.title = 'Instrument types failed to load; please type manually if needed.';
                        }
                    });
                }
            }
        }

        populateTransactionTypeSelects() {
            if (!Array.isArray(this.instrumentTypeOptions) || this.instrumentTypeOptions.length === 0) {
                return;
            }

            const selects = this.transactionTypeSelects.length
                ? this.transactionTypeSelects
                : Array.from(this.container.querySelectorAll('select[data-role="transaction-type"]'));

            const currentValue = this.state.transactionType || '';

            selects.forEach((select) => {
                const placeholder = select.dataset.placeholder || 'Select type';
                const previousValue = currentValue || select.value || select.dataset.initialValue || '';

                while (select.firstChild) {
                    select.removeChild(select.firstChild);
                }

                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                select.appendChild(placeholderOption);

                const existingFallbacks = select.querySelectorAll('option[data-fallback="true"]');
                existingFallbacks.forEach((option) => option.remove());

                const normalizedOptions = this.instrumentTypeOptions
                    .map((item) => {
                        const name = (item && (item.name || item.InstrumentName)) ? String(item.name || item.InstrumentName).trim() : '';
                        return name ? { value: name, label: name } : null;
                    })
                    .filter(Boolean)
                    .sort((a, b) => a.label.localeCompare(b.label));

                let hasMatch = false;

                normalizedOptions.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    select.appendChild(option);
                    if (!hasMatch && previousValue && item.value.toLowerCase() === previousValue.toLowerCase()) {
                        hasMatch = true;
                    }
                });

                if (previousValue && !hasMatch) {
                    const fallback = document.createElement('option');
                    fallback.value = previousValue;
                    fallback.textContent = previousValue;
                    fallback.dataset.fallback = 'true';
                    select.appendChild(fallback);
                }

                select.value = previousValue;
            });

            this.updateTransactionContext();
        }

        syncModelElement(key, value) {
            const element = this.modelElements.get(key);
            if (!element) {
                return;
            }

            if (element.type === 'checkbox') {
                element.checked = Boolean(value);
                return;
            }

            if (element.value !== value) {
                element.value = value == null ? '' : value;
            }
        }

        syncHasOfficialField(value) {
            const element = this.modelElements.get('hasOfficialFileNumber');
            if (element) {
                element.value = value ? '1' : '0';
            }
            if (this.toggleTempCheckbox) {
                this.toggleTempCheckbox.checked = !value;
            }
        }

        syncAllState() {
            Object.keys(this.state).forEach((key) => {
                this.onStateChange(key, this.state[key], { silent: true });
            });
        }

        resetForm() {
            // Invalidate any in-flight matching-records fetch so stale results
            // cannot re-populate the "Existing Records for this File" card.
            this.matchingRecordsToken = (this.matchingRecordsToken || 0) + 1;

            if (this.form) {
                this.form.reset();
            }

            this.exitUpdateMode();

            // Force-clear the file number & related file number inputs in case
            // they carry default values that form.reset() would otherwise restore.
            const clearKeys = ['fileNumber', 'relatedFileNumber', 'fileno', 'related_file_number'];
            clearKeys.forEach((key) => {
                const el = this.modelElements.get(key);
                if (el && 'value' in el) {
                    el.value = '';
                }
            });

            if (this.matchingRecordsResults) {
                this.matchingRecordsResults.innerHTML = '';
            }
            if (this.matchingRecordsContainer) {
                this.matchingRecordsContainer.classList.add('hidden');
                const countLabel = this.matchingRecordsContainer.querySelector('[data-role="matching-records-count"]');
                if (countLabel) countLabel.textContent = '';
            }

            this.instrumentEntries = [];

            this.modelElements.forEach((element, key) => {
                this.state[key] = this.extractElementValue(element, key);
            });

            // Explicitly blank out file number state so the fileNumber onStateChange
            // dispatch takes the "empty" branch and hides the matching-records card.
            this.state.fileNumber = '';
            this.state.tempFileNumber = '';
            this.state.tempFileNumberLoading = false;
            this.state.tempFileNumberError = '';
            this.state.isMapped = false;

            this.syncAllState();
            this.updateTransactionContext();
            this.updatePropertyDescription();
            this.updateRegPreview();
            this.syncFileNumberSections();
            this.updateTempStatus();
            this.renderInstruments();

            // Belt-and-suspenders: after all sync handlers ran, ensure the
            // "Existing Records for this File" card is empty and hidden.
            if (this.matchingRecordsResults) {
                this.matchingRecordsResults.innerHTML = '';
            }
            if (this.matchingRecordsContainer) {
                this.matchingRecordsContainer.classList.add('hidden');
            }

            // Also clear the CofO/PRA duplicate lock cards if the blade helpers exist.
            try {
                if (typeof window.unlockPropertyRecordForm === 'function' && this.form) {
                    window.unlockPropertyRecordForm(this.form, { silent: true });
                }
                if (typeof window.clearCofoDuplicateCard === 'function') {
                    window.clearCofoDuplicateCard();
                }
                if (typeof window.clearPraDuplicateCard === 'function') {
                    window.clearPraDuplicateCard();
                }
            } catch (e) {
                // non-fatal: blade helpers are optional
            }
        }

        applyFormMode(mode) {
            const normalizedMode = mode === 'index' ? 'index' : 'property';
            if (this.modelElements.has('formMode')) {
                const hidden = this.modelElements.get('formMode');
                hidden.value = normalizedMode;
            }

            this.optionalBadges.forEach((badge) => {
                badge.classList.toggle('hidden', normalizedMode !== 'property');
            });

            const isIndexMode = normalizedMode === 'index';
            this.indexModeElements.forEach((element) => {
                const indexClass = element.dataset.indexClass || '';
                const defaultClass = element.dataset.defaultClass || '';
                const indexPlaceholder = element.dataset.indexPlaceholder || '';
                const defaultPlaceholder = element.dataset.placeholderDefault || '';

                element.className = isIndexMode
                    ? `${defaultClass} ${indexClass}`.trim()
                    : defaultClass;

                const placeholder = isIndexMode && indexPlaceholder ? indexPlaceholder : defaultPlaceholder;
                if (placeholder) {
                    element.setAttribute('placeholder', placeholder);
                } else {
                    element.removeAttribute('placeholder');
                }
            });

            const titleElement = this.dialog ? this.dialog.querySelector('#form-title') : null;
            if (titleElement) {
                if (titleElement.dataset.customTitle) {
                    titleElement.textContent = titleElement.dataset.customTitle;
                } else {
                    titleElement.textContent = normalizedMode === 'index' ? 'Index Card' : 'Add New Property Record';
                }
            }
        }

        updateTransactionContext() {
            // Lock the Instrument Type dropdown to "Deed of Mortgage" on the instruments capture page
            if (window.location.pathname.includes('/instruments/create')) {
                const selectElement = document.getElementById('transactionType-record');
                if (selectElement) {
                    selectElement.style.pointerEvents = 'none';
                    selectElement.style.backgroundColor = '#f3f4f6';
                    selectElement.setAttribute('tabindex', '-1');
                    if (this.state.transactionType !== 'Deed of Mortgage') {
                        this.state.transactionType = 'Deed of Mortgage';
                        this.syncModelElement('transactionType', 'Deed of Mortgage');
                        this.syncTransactionTypeSelects('Deed of Mortgage');
                    }
                }
            }

            const transactionType = this.state.transactionType || '';
            const labels = TRANSACTION_PARTY_LABELS[transactionType] || { first: 'Grantor', second: 'Grantee' };
            const normalizedTx = String(transactionType || '').toLowerCase();
            const isOpTransaction = normalizedTx.includes('occupancy permit')
                || normalizedTx.includes('occupation permit')
                || normalizedTx === 'op';
            const isCofOTransaction = normalizedTx.includes('certificate of occupancy');

            const updateLabelText = (labelElement, text) => {
                if (!labelElement || !text) return;
                const span = labelElement.querySelector('[data-role="label-text"]');
                if (span) {
                    span.textContent = text;
                } else {
                    labelElement.textContent = text;
                }
            };

            if (this.partyLabelFirst && labels.first) {
                updateLabelText(this.partyLabelFirst, labels.first);
            }
            if (this.partyLabelSecond && labels.second) {
                updateLabelText(this.partyLabelSecond, labels.second);
            }
            if (this.partyLabelThird) {
                const isSurrender = transactionType === 'Deed of Surrender and Release';
                const hasTripartite = Boolean(this.state.tripartiteHasThird);
                const thirdLabel = isSurrender && hasTripartite ? (labels.second || 'Surrenderee') : (labels.third || 'Third Party');
                updateLabelText(this.partyLabelThird, thirdLabel);
            }

            if (this.partyLabelFourth && labels.fourth) {
                updateLabelText(this.partyLabelFourth, labels.fourth);
            }

            if (this.partyInputFirst) {
                this.partyInputFirst.name = FIRST_PARTY_FIELD_MAP[transactionType] || 'Grantor';
                const labelLower = labels.first.toLowerCase();
                this.partyInputFirst.setAttribute('placeholder', `Enter ${labelLower} name`);
            }

            if (this.partyInputSecond) {
                this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Grantee';
                const labelLower = labels.second.toLowerCase();
                this.partyInputSecond.setAttribute('placeholder', `Enter ${labelLower} name`);
            }

            if (this.partyInputThird) {
                const thirdLabel = labels.third || 'Third Party';
                this.partyInputThird.setAttribute('placeholder', `Enter ${thirdLabel.toLowerCase()} name`);
            }

            if (this.partyInputFourth) {
                const fourthLabel = labels.fourth || 'Fourth Party';
                this.partyInputFourth.setAttribute('placeholder', `Enter ${fourthLabel.toLowerCase()} name`);
            }

            const isGovernment = GOVERNMENT_TRANSACTION_TYPES.includes(transactionType);
            if (this.partyInputFirst) {
                if (isGovernment) {
                    this.partyInputFirst.value = 'KANO STATE GOVERNMENT';
                    this.partyInputFirst.readOnly = true;
                    this.partyInputFirst.classList.add('bg-gray-100', 'text-gray-800');
                    this.setState('firstParty', 'KANO STATE GOVERNMENT');
                } else {
                    if (this.partyInputFirst.readOnly) {
                        this.setState('firstParty', '');
                    }
                    this.partyInputFirst.readOnly = false;
                    this.partyInputFirst.classList.remove('bg-gray-100', 'text-gray-800');
                }
            }

            if (this.opFieldsWrapper) {
                this.opFieldsWrapper.classList.toggle('hidden', !isOpTransaction);
            }

            if (!isOpTransaction) {
                this.setState('opType', '', { silent: true });
                this.syncModelElement('opType', '');
                this.setState('opSerialNumber', '', { silent: true });
                this.syncModelElement('opSerialNumber', '');
            }

            if (this.cofoFieldsWrapper) {
                this.cofoFieldsWrapper.classList.toggle('hidden', !isCofOTransaction);
            }

            if (!isCofOTransaction) {
                this.setState('cofoType', '', { silent: true });
                this.syncModelElement('cofoType', '');
            }

            if (this.transactionDetailsSection) {
                this.transactionDetailsSection.classList.toggle('hidden', transactionType === '');
            }

            if (this.tripartiteToggleLabelText) {
                if (transactionType === 'Tripartite Mortgage' || transactionType === 'Deed of Mortgage') {
                    this.tripartiteToggleLabelText.textContent = 'Include Co-Mortgagor (three-party agreement)';
                } else if (transactionType === 'Deed of Surrender and Release') {
                    this.tripartiteToggleLabelText.textContent = 'Include Co-Surrenderor (three-party agreement)';
                }
            }

            this.updateTripartiteControls();
        }

        updateTripartiteControls() {
            const transactionType = this.state.transactionType || '';
            const isTripartiteDropdown = transactionType === 'Tripartite Mortgage';
            const isMortgage = transactionType === 'Deed of Mortgage';
            const isSurrender = transactionType === 'Deed of Surrender and Release';

            const checkboxElement = this.modelElements.get('tripartiteHasThird');
            const fourthPartyCheckbox = this.modelElements.get('hasFourthParty');
            const labels = TRANSACTION_PARTY_LABELS[transactionType] || {};

            if (this.tripartiteToggleWrapper) {
                this.tripartiteToggleWrapper.classList.toggle('hidden', !(isTripartiteDropdown || isSurrender || isMortgage));
            }

            let hasToggleChecked = Boolean(this.state.tripartiteHasThird);
            const fourthValue = normalizeText(this.state.fourthParty);

            // Auto-check if P4 has value in Mortgage mode
            if (isTripartiteDropdown && fourthValue && !hasToggleChecked) {
                hasToggleChecked = true;
                this.state.tripartiteHasThird = true;
                if (checkboxElement) {
                    checkboxElement.checked = true;
                }
                this.syncModelElement('tripartiteHasThird', true);
            }

            // Sync uncheck if moving away from Tripartite types
            if (!(isTripartiteDropdown || isSurrender || isMortgage) && hasToggleChecked) {
                hasToggleChecked = false;
                this.state.tripartiteHasThird = false;
                if (checkboxElement) {
                    checkboxElement.checked = false;
                }
                this.syncModelElement('tripartiteHasThird', false);
            }

            let hasFourthPartyChecked = Boolean(this.state.hasFourthParty);
            const fourthValueActual = normalizeText(this.state.fourthParty);

            // Auto-check if P4 has value in Mortgage mode
            if ((isTripartiteDropdown || isMortgage) && fourthValueActual && !hasFourthPartyChecked) {
                hasFourthPartyChecked = true;
                this.state.hasFourthParty = true;
                if (fourthPartyCheckbox) {
                    fourthPartyCheckbox.checked = true;
                }
                this.syncModelElement('hasFourthParty', true);
            }

            // Sync uncheck if moving away from Mortgage types
            if (!(isTripartiteDropdown || isMortgage) && hasFourthPartyChecked) {
                hasFourthPartyChecked = false;
                this.state.hasFourthParty = false;
                if (fourthPartyCheckbox) {
                    fourthPartyCheckbox.checked = false;
                }
                this.syncModelElement('hasFourthParty', false);
            }

            // Hide Fourth Party toggle by default
            if (this.fourthPartyToggleWrapper) {
                this.fourthPartyToggleWrapper.classList.add('hidden');
            }

            // Logic for showing 3rd/4th party fields:
            // - Tripartite/Deed of Mortgage: Base is P1, P2. 
            // - Toggle 1 (Co-mortgagor) -> Shows P3.
            // - Toggle 2 (Include Fourth Party) -> Shows P4.
            let shouldShowThird = false;
            let shouldShowFourth = false;

            if (isTripartiteDropdown) {
                shouldShowThird = true;
                if (this.fourthPartyToggleWrapper) {
                    this.fourthPartyToggleWrapper.classList.remove('hidden');
                }
                if (hasFourthPartyChecked) {
                    shouldShowFourth = true;
                }
            } else if (isMortgage) {
                if (hasToggleChecked) {
                    shouldShowThird = true;
                    if (this.fourthPartyToggleWrapper) {
                        this.fourthPartyToggleWrapper.classList.remove('hidden');
                    }
                    if (hasFourthPartyChecked) {
                        shouldShowFourth = true;
                    }
                }
            } else if (isSurrender) {
                if (hasToggleChecked) {
                    shouldShowThird = true;
                }
            }

            if (this.partyThirdWrapper) {
                this.partyThirdWrapper.classList.toggle('hidden', !shouldShowThird);
            }
            if (this.partyFourthWrapper) {
                this.partyFourthWrapper.classList.toggle('hidden', !shouldShowFourth);
            }

            // Handle P4 (Mortgagor 3)
            if (!shouldShowFourth) {
                if (this.partyInputFourth) {
                    this.partyInputFourth.value = '';
                    this.partyInputFourth.name = '';
                    this.syncModelElement('party4', '');
                    this.state.party4 = '';
                    this.state.fourthParty = '';
                }
            } else if (this.partyInputFourth) {
                this.partyInputFourth.name = 'Mortgagor_3';
            }

            if (shouldShowThird) {
                if (this.partyInputThird) {
                    if (isSurrender) {
                        // For Surrender: P1: Surrenderor, P2: Co-Surrenderor, P3: Surrenderee
                        // Mapping: UI partyInputThird (thirdParty) becomes Co-Surrenderor (Surrenderor_2), 
                        // partyInputSecond (secondParty) becomes Surrenderee.
                        this.partyInputThird.name = THIRD_PARTY_FIELD_MAP[transactionType] || 'Surrenderor_2';
                    } else {
                        // For Mortgage: P1: Mortgagor, P2: Mortgagee, P3: Co-mortgagor
                        // UI: partyInputThird (thirdParty) is Co-mortgagor
                        this.partyInputThird.name = THIRD_PARTY_FIELD_MAP[transactionType] || 'Mortgagor_2';
                    }
                }

                if (this.partyInputSecond) {
                    if (isSurrender) {
                        this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Surrenderee';
                    } else {
                        this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Mortgagee';
                    }
                }

                // Update Labels
                if (this.partyLabelSecond) {
                    this.partyLabelSecond.textContent = isSurrender ? 'Surrenderee' : (labels.second || 'Second Party');
                }
                if (this.partyLabelThird) {
                    this.partyLabelThird.textContent = isSurrender ? 'Co-Surrenderor' : (labels.third || 'Co-mortgagor');
                }

                // Update Placeholders
                if (this.partyInputSecond) {
                    const l2 = isSurrender ? 'surrenderee' : (labels.second || 'second party').toLowerCase();
                    this.partyInputSecond.setAttribute('placeholder', `Enter ${l2} name`);
                }
                if (this.partyInputThird) {
                    const l3 = isSurrender ? 'co-surrenderor' : (labels.third || 'co-mortgagor').toLowerCase();
                    this.partyInputThird.setAttribute('placeholder', `Enter ${l3} name`);
                }
            } else {
                // Restore default names and labels when not in tripartite-collapsed mode
                if (this.partyInputSecond) {
                    this.partyInputSecond.name = SECOND_PARTY_FIELD_MAP[transactionType] || 'Grantee';
                }
                if (this.partyLabelSecond) {
                    this.partyLabelSecond.textContent = labels.second || 'Second Party';
                }
                if (this.partyInputSecond) {
                    const labelLower = (labels.second || 'second party').toLowerCase();
                    this.partyInputSecond.setAttribute('placeholder', `Enter ${labelLower} name`);
                }

                // Clear Co-mortgagor/Additional party when showing Fourth Party instead or when Bipartite
                if (this.partyInputThird) {
                    this.partyInputThird.value = '';
                    this.partyInputThird.name = '';
                }
                const thirdValue = normalizeText(this.state.thirdParty);
                if (thirdValue) {
                    this.setState('thirdParty', '', { silent: true });
                    this.syncModelElement('thirdParty', '');
                }
            }
        }

        async checkFilenoForMortgages(fileno, autoSelect = false) {
            if (!fileno) return;
            const warningNode = this.container.querySelector('[data-role="mortgage-warning"]');
            // Try to fetch mortgages count and also detect surrender/release records
            try {
                const mortgageResp = await fetch(`/api/file-mortgages?fileno=${encodeURIComponent(fileno)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const mortgagePayload = mortgageResp.ok ? await mortgageResp.json() : null;
                const mortgageCount = Number(mortgagePayload?.count || 0);

                // Check PRA record for Surrender and Release entries
                const praResp = await fetch(`/api/pra/v1/records/by-file/${encodeURIComponent(fileno)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const praPayload = praResp.ok ? await praResp.json() : null;
                const existingType = (praPayload && praPayload.data && praPayload.data.transaction_type) ? String(praPayload.data.transaction_type).toLowerCase() : '';
                const hasSurrender = existingType === 'deed of surrender and release' || existingType === 'surrender' || existingType === 'deed of surrender';

                // Auto-select transaction type based on history
                if (autoSelect) {
                    if (mortgageCount > 0) {
                        const currentType = String(this.state.transactionType).toLowerCase();
                        if (currentType !== 'deed of surrender and release') {
                            this.setState('transactionType', 'Deed of Surrender and Release');
                        }
                    } else if (hasSurrender) {
                        const currentType = String(this.state.transactionType).toLowerCase();
                        if (currentType !== 'deed of mortgage') {
                            this.setState('transactionType', 'Deed of Mortgage');
                        }
                    }
                }

                // Update hidden state and warning node
                const anyRelated = mortgageCount > 0 || hasSurrender;
                this.setState('isMapped', anyRelated);
                if (warningNode) {
                    warningNode.classList.toggle('hidden', !anyRelated);
                }
            } catch (err) {
                console.warn('Related-deeds lookup failed', err);
            }
        }

        async fetchAndDisplayMatchingRecords(fileno) {
            if (!fileno || !this.matchingRecordsResults) return;

            // Snapshot the reset token so a Refresh during flight discards results.
            const requestToken = this.matchingRecordsToken || 0;

            try {
                // **PIC/PRA ROUTING FIX**: Add record_mode to query params
                const recordModeInput = this.container.querySelector('input[name="record_mode"]');
                const recordMode = recordModeInput ? recordModeInput.value : '';
                const queryParams = new URLSearchParams({ record_mode: recordMode });

                const response = await fetch(`/api/pra/v1/records/all-by-file/${encodeURIComponent(fileno)}?${queryParams.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });

                const payload = response.ok ? await response.json() : null;
                const records = payload?.data || [];

                // Refresh was clicked while the request was in flight — bail out.
                if ((this.matchingRecordsToken || 0) !== requestToken) {
                    if (this.matchingRecordsContainer) this.matchingRecordsContainer.classList.add('hidden');
                    if (this.matchingRecordsResults) this.matchingRecordsResults.innerHTML = '';
                    return;
                }

                if (records.length === 0) {
                    if (this.matchingRecordsContainer) this.matchingRecordsContainer.classList.add('hidden');
                    return;
                }

                this.renderMatchingRecords(records);
                if (this.matchingRecordsContainer) this.matchingRecordsContainer.classList.remove('hidden');

            } catch (err) {
                console.warn('Failed to fetch matching records', err);
            }
        }

        /**
         * Auto-backfill property details from any existing record on the file.
         *
         * Used by the "Capture Existing Mortgage Now" flow (No Existing Mortgage
         * Found! prompt): once a file number is selected we pull the shared
         * property attributes (location, plot, district, land use, etc.) from the
         * file's existing records so the user does not have to click a matching
         * record first. Property details are identical across a file's
         * instruments, so the first record that actually carries property data
         * is sufficient.
         */
        async autoBackfillPropertyDetailsFromFile(fileno) {
            const normalized = normalizeText(fileno);
            if (!normalized) return false;

            // Primary source: the file's indexing record holds the canonical
            // property attributes (plot, TP, LPKN, district, LGA, land use, etc.).
            try {
                const response = await fetch(`/api/pra/v1/records/property-by-file/${encodeURIComponent(normalized)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });

                const payload = response.ok ? await response.json() : null;
                const details = payload?.data || null;
                if (details && Object.values(details).some((v) => normalizeText(v).length > 0)) {
                    return this.applyIndexedPropertyDetails(details);
                }
            } catch (err) {
                console.warn('Failed to backfill property details from file indexing', err);
            }

            // Fallback: derive property details from an existing PRA record on the
            // file when the file has no indexing row.
            try {
                const recordModeInput = this.container.querySelector('input[name="record_mode"]');
                const recordMode = recordModeInput ? recordModeInput.value : '';
                const queryParams = new URLSearchParams({ record_mode: recordMode });

                const response = await fetch(`/api/pra/v1/records/all-by-file/${encodeURIComponent(normalized)}?${queryParams.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });

                const payload = response.ok ? await response.json() : null;
                const records = payload?.data || [];
                if (records.length === 0) return false;

                const propertyKeys = ['location', 'plot_no', 'plotNo', 'house_no', 'houseNo', 'street_name', 'streetName', 'district', 'land_use', 'landUse', 'property_description', 'propertyDescription'];
                const hasPropertyData = (rec) => propertyKeys.some((k) => normalizeText(rec[k]).length > 0);
                const rec = records.find(hasPropertyData) || records[0];

                return this.applyIndexedPropertyDetails({
                    house_no: rec.house_no ?? rec.houseNo,
                    plot_no: rec.plot_no ?? rec.plotNo,
                    tp_no: rec.tp_no ?? rec.tpNo,
                    lpkn_no: rec.lpkn_no ?? rec.lpknNo,
                    street_name: rec.street_name ?? rec.streetName,
                    district: rec.district,
                    lga: rec.lga,
                    location: rec.location,
                    land_use: rec.land_use ?? rec.landUse,
                    property_description: rec.property_description ?? rec.propertyDescription,
                });
            } catch (err) {
                console.warn('Failed to auto-backfill property details for file', err);
                return false;
            }
        }

        /**
         * Apply backfilled property details to the form, driving the cascading
         * Street/District selects (with their "Other" free-text fallback) and the
         * LGA / Land Use selects — not just plain inputs. Indexed values often do
         * not exactly match a dropdown option (e.g. "Dan Dinshe Kofar Dawannau"
         * vs the listed "DAN DINSHE KOFAR DAWANAU"), so unmatched values route
         * through the component's "Other" input to stay visible and submittable.
         */
        applyIndexedPropertyDetails(details) {
            if (!details) return false;
            let applied = false;

            const setText = (stateKey, value) => {
                if (normalizeText(value).length > 0) {
                    this.setState(stateKey, value);
                    applied = true;
                }
            };

            setText('houseNo', details.house_no);
            setText('plotNo', details.plot_no);
            setText('tpNo', details.tp_no);
            setText('lpknNo', details.lpkn_no);

            if (normalizeText(details.street_name).length > 0) {
                this.applyComponentSelectValue(this.streetComponent, 'street-select', 'street-other', details.street_name);
                applied = true;
            }
            if (normalizeText(details.district).length > 0) {
                this.applyComponentSelectValue(this.districtComponent, 'district-select', 'district-other', details.district);
                applied = true;
            }
            if (normalizeText(details.lga).length > 0) {
                if (this.applyPlainSelectValue('lga', details.lga)) applied = true;
            }
            if (normalizeText(details.land_use).length > 0) {
                if (this.applyPlainSelectValue('landUse', details.land_use)) applied = true;
            }

            // Rebuild the description from the components just set; only fall back
            // to the stored/indexed value when nothing could be derived.
            this.updatePropertyDescription();
            const derived = normalizeText(this.state.propertyDescription);
            const stored = normalizeText(details.property_description) || normalizeText(details.location);
            if (derived.length === 0 && stored.length > 0) {
                this.setState('propertyDescription', stored);
                applied = true;
            }

            return applied;
        }

        /**
         * Select a value on a Street/District component: match an existing option
         * case-insensitively (by value or label); otherwise switch the select to
         * "Other" and place the value in the free-text input. Dispatches the
         * native events the component already listens to so state + property
         * description stay in sync.
         */
        applyComponentSelectValue(component, selectRole, otherRole, value) {
            if (!component) return;
            const select = component.querySelector(`[data-role="${selectRole}"]`);
            const otherInput = component.querySelector(`[data-role="${otherRole}"]`);
            if (!select) return;

            const target = normalizeText(value).toLowerCase();
            const match = Array.from(select.options).find((o) =>
                normalizeText(o.value).toLowerCase() === target ||
                normalizeText(o.textContent).toLowerCase() === target
            );

            if (match) {
                select.value = match.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (otherInput) {
                select.value = 'other';
                select.dispatchEvent(new Event('change', { bubbles: true }));
                otherInput.value = normalizeText(value);
                otherInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        /**
         * Select a value on a plain model-bound <select> (LGA, Land Use) by
         * case-insensitive match on option value or label. Returns whether a
         * matching option was found and applied.
         */
        applyPlainSelectValue(modelKey, value) {
            const select = this.modelElements.get(modelKey);
            if (!select || !select.options) return false;

            const target = normalizeText(value).toLowerCase();
            const match = Array.from(select.options).find((o) =>
                normalizeText(o.value).toLowerCase() === target ||
                normalizeText(o.textContent).toLowerCase() === target
            );

            if (!match) return false;

            select.value = match.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        renderMatchingRecords(records) {
            this.matchingRecordsResults.innerHTML = '';

            const countLabel = this.matchingRecordsContainer.querySelector('[data-role="matching-records-count"]');
            if (countLabel) {
                countLabel.textContent = `(${records.length})`;
            }

            records.forEach((record, index) => {
                const item = document.createElement('div');
                item.className = 'p-3 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-blue-200 transition-all duration-200';

                const type = record.transaction_type || record.instrument_type || 'Unknown Type';
                const regNo = record.regNo || record.Prop_id || record.prop_id || 'No Reg #';
                const parties = this.getExtendedPartiesSummary(record);
                const dateInfo = this.getDateInfoSummary(record);

                const isMortgage = type.toLowerCase().includes('mortgage');
                const isSurrender = type.toLowerCase().includes('surrender') || type.toLowerCase().includes('release');
                let badgeHtml = '';
                if (isMortgage) {
                    badgeHtml = '<span class="ml-1 px-1.5 py-0.5 text-[8px] font-bold bg-amber-100 text-amber-700 rounded border border-amber-200 uppercase">Mortgage</span>';
                } else if (isSurrender) {
                    badgeHtml = '<span class="ml-1 px-1.5 py-0.5 text-[8px] font-bold bg-purple-100 text-purple-700 rounded border border-purple-200 uppercase">Surrender</span>';
                }

                item.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1 min-w-0 pr-2">
                            <div class="flex items-center">
                                <span class="mr-1.5 text-red-600 font-bold text-[10px]">${index + 1}.</span>
                                <div class="text-[11px] font-bold text-gray-800 truncate">${type}</div>
                                ${badgeHtml}
                            </div>
                            <div class="text-[10px] text-gray-500 font-medium"><span class="font-bold text-gray-700">Registration Particulars:</span> <span class="text-blue-900 font-bold">${regNo}</span></div>
                            ${dateInfo ? `<div class="text-[10px] text-blue-600 font-semibold mt-0.5">${dateInfo}</div>` : ''}
                        </div>
                        <div class="flex space-x-1 shrink-0">
                            <button type="button" class="px-2 py-1 text-[9px] font-bold text-white bg-blue-500 rounded hover:bg-blue-600 transition-colors shadow-sm" data-action="update-record">Update</button>
                            <button type="button" class="px-2 py-1 text-[9px] font-bold text-white bg-emerald-500 rounded hover:bg-emerald-600 transition-colors shadow-sm" data-action="capture-new">Capture New</button>
                        </div>
                    </div>
                    ${parties ? `<div class="text-[9px] text-gray-600 border-t border-gray-50 pt-1.5 mt-1.5 leading-tight">${parties}</div>` : ''}
                `;

                item.querySelector('[data-action="update-record"]').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleMatchingRecordSelect(record, 'update');
                });

                item.querySelector('[data-action="capture-new"]').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleMatchingRecordSelect(record, 'capture');
                });

                this.matchingRecordsResults.appendChild(item);
            });
        }

        getExtendedPartiesSummary(record) {
            const parts = [];
            // Use parties from API if available
            if (record.parties) {
                const p = record.parties;
                if (p.party_1) parts.push(`<span class="font-semibold text-gray-700">P1:</span> ${p.party_1}`);
                if (p.party_2) parts.push(`<span class="font-semibold text-gray-700">P2:</span> ${p.party_2}`);
                if (p.party_3) parts.push(`<span class="font-semibold text-gray-700">P3:</span> ${p.party_3}`);
            }

            // Fallback to legacy fields if parties is empty
            if (parts.length === 0) {
                const primary = record.Assignor || record.Mortgagor || record.Grantor || record.Lessor || record.Surrenderor;
                const secondary = record.Assignee || record.Mortgagee || record.Grantee || record.Lessee || record.Surrenderee;
                if (primary) parts.push(`<span class="font-semibold text-gray-700">From:</span> ${primary}`);
                if (secondary) parts.push(`<span class="font-semibold text-gray-700">To:</span> ${secondary}`);
            }

            return parts.join(' <span class="text-gray-300 mx-1">|</span> ');
        }

        getPartiesSummary(record) {
            return this.getExtendedPartiesSummary(record);
        }

        getDateInfoSummary(record) {
            const parts = [];
            const date = record.deeds_date || record.deedsDate || record.transaction_date || record.transactionDate;
            const time = record.deeds_time || record.deedsTime;

            if (date) parts.push(`<span class="font-bold text-gray-700">Deed Date:</span> <span class="text-blue-700">${date}</span>`);
            if (time) parts.push(`<span class="font-bold text-gray-700">Deed Time:</span> <span class="text-blue-700">${time}</span>`);

            return parts.length > 0 ? parts.join(' <span class="text-gray-300 mx-1">|</span> ') : null;
        }

        handleMatchingRecordSelect(record, action) {
            // Hide the container
            if (this.matchingRecordsContainer) {
                this.matchingRecordsContainer.classList.add('hidden');
            }

            if (action === 'update') {
                const label = this.getRecordDisplayLabel(record);
                const recordId = this.resolveRecordId(record);
                this.enterUpdateMode(record, label, recordId);

                // Populate all fields from the record
                this.prefillFormFromRecord(record);
            } else {
                // Capture New: keep form in 'add' mode but populate property details
                this.exitUpdateMode();
                this.prefillPropertyDetailsFromRecord(record);
            }
        }

        prefillFormFromRecord(record) {
            // First pass: basic flat mapping
            Object.entries(record).forEach(([key, value]) => {
                if (value !== null && value !== undefined) {
                    this.setState(key, value);
                }
            });

            // Second pass: explicit mappings for known differences
            const mapping = {
                'transaction_type': 'transactionType',
                'deeds_date': 'deedsDate',
                'deeds_time': 'deedsTime',
                'op_type': 'opType',
                'op_serial_number': 'opSerialNumber',
                'cofo_type': 'cofoType',
                'transaction_date': 'transactionDate',
                'land_use': 'landUse',
                'property_description': 'propertyDescription',
                'house_no': 'houseNo',
                'plot_no': 'plotNo',
                'street_name': 'streetName',
                'tp_no': 'tpNo',
                'lpkn_no': 'lpknNo',
                'approved_plan_no': 'approvedPlanNo',
                'plot_size': 'plotSize',
                'date_recommended': 'dateRecommended',
                'date_approved': 'dateApproved',
                'lease_begins': 'leaseBegins',
                'lease_expires': 'leaseExpires',
                'metric_sheet': 'metricSheet'
            };

            Object.entries(mapping).forEach(([recordKey, stateKey]) => {
                if (record[recordKey] !== undefined && record[recordKey] !== null) {
                    this.setState(stateKey, record[recordKey]);
                }
            });

            // Handle parties
            if (record.parties) {
                const parties = record.parties;
                // Map logical parties to form fields
                if (parties.party_1) this.setState('party1', parties.party_1);
                if (parties.party_2) this.setState('party2', parties.party_2);
                if (parties.party_3) this.setState('party3', parties.party_3);
                if (parties.party_4) this.setState('party4', parties.party_4);

                const primary = parties.assignor || parties.mortgagor || parties.grantor || parties.lessor || parties.surrenderor || parties.party_1;
                const secondary = parties.assignee || parties.mortgagee || parties.grantee || parties.lessee || parties.surrenderee || parties.party_2;

                if (primary) this.setState('firstParty', primary);
                if (secondary) this.setState('secondParty', secondary);
            }
        }

        prefillPropertyDetailsFromRecord(record) {
            const propertyFieldsMapping = {
                'houseNo': 'houseNo',
                'house_no': 'houseNo',
                'plotNo': 'plotNo',
                'plot_no': 'plotNo',
                'tpNo': 'tpNo',
                'tp_no': 'tpNo',
                'lpknNo': 'lpknNo',
                'lpkn_no': 'lpknNo',
                'approvedPlanNo': 'approvedPlanNo',
                'approved_plan_no': 'approvedPlanNo',
                'plotSize': 'plotSize',
                'plot_size': 'plotSize',
                'propertyDescription': 'propertyDescription',
                'property_description': 'propertyDescription',
                'location': 'location',
                'landUse': 'landUse',
                'land_use': 'landUse',
                'district': 'district',
                'streetName': 'streetName',
                'street_name': 'streetName',
                'lga': 'lga',
                'state': 'state'
            };

            Object.entries(propertyFieldsMapping).forEach(([recordKey, stateKey]) => {
                if (record[recordKey] !== undefined && record[recordKey] !== null) {
                    this.setState(stateKey, record[recordKey]);
                }
            });
        }

        updatePropertyDescription() {
            const parts = [];
            const placeholders = ['Select Street Name', 'Select District Name', 'Select LGA'];

            const isPlaceholder = (val) => {
                if (!val) return true;
                return placeholders.some(p => val.includes(p));
            };

            const house = normalizeText(this.state.houseNo);
            if (house) {
                parts.push(house);
            }

            const street = normalizeText(this.state.currentStreetName || this.state.streetName);
            if (street && !isPlaceholder(street)) {
                parts.push(street);
            }

            const district = normalizeText(this.state.currentDistrict || this.state.district);
            if (district && !isPlaceholder(district)) {
                parts.push(district);
            }

            const lga = normalizeText(this.state.lga);
            if (lga && !isPlaceholder(lga)) {
                parts.push(lga);
            }

            const stateName = normalizeText(this.state.state);
            if (stateName) {
                parts.push(stateName);
            }

            const description = parts.join(', ');
            if (this.state.propertyDescription !== description) {
                this.state.propertyDescription = description;
                this.syncModelElement('propertyDescription', description);
            }
        }

        updateRegPreview() {
            const parts = [this.state.serialNo, this.state.pageNo, this.state.volumeNo]
                .map((value) => normalizeText(value))
                .filter((value) => value.length > 0);
            const preview = parts.join('/');

            if (this.regPreviewValue) {
                this.regPreviewValue.textContent = preview;
            }

            if (this.regPreviewBox) {
                this.regPreviewBox.classList.toggle('hidden', preview.length === 0);
            }
        }

        syncFileNumberSections() {
            const hasOfficial = !!this.state.hasOfficialFileNumber;
            if (this.smartWrapper) {
                this.smartWrapper.classList.toggle('hidden', !hasOfficial);
            }
            if (this.tempWrapper) {
                this.tempWrapper.classList.toggle('hidden', hasOfficial);
            }
            if (this.modalMlsMethodToggle) {
                this.modalMlsMethodToggle.classList.toggle('hidden', !hasOfficial);
            }

            if (!hasOfficial) {
                this.modalMlsInputSections.forEach(section => {
                    section.classList.add('hidden');
                });
            } else if (window.GlobalFileNoModal && typeof window.GlobalFileNoModal.initializeTabUI === 'function') {
                window.GlobalFileNoModal.initializeTabUI('mls');
            }

            if (this.fileNumberMessage) {
                this.fileNumberMessage.classList.toggle('hidden', hasOfficial);
            }
        }

        bindFileNumberControls() {
            if (this.toggleTempCheckbox) {
                this.toggleTempCheckbox.addEventListener('change', (event) => {
                    const checked = Boolean(event.target.checked);
                    this.setState('hasOfficialFileNumber', !checked);
                    if (checked && !this.state.tempFileNumber) {
                        this.fetchTempFileNumber(true);
                    }
                });
            }

            if (this.refreshTempButton) {
                this.refreshTempButton.addEventListener('click', () => {
                    this.fetchTempFileNumber(true);
                });
            }
            // Bind the main fileno hidden input (smart selector writes to #fileno)
            try {
                const filenoInput = this.container.querySelector('#fileno') || document.getElementById('fileno');
                if (filenoInput) {
                    const handler = (event) => {
                        const value = (event && event.target && event.target.value) ? String(event.target.value).trim() : String(filenoInput.value || '').trim();
                        // update controller state so onStateChange('fileNumber') triggers mortgage check when appropriate
                        this.setState('fileNumber', value);
                    };
                    filenoInput.addEventListener('input', handler);
                    filenoInput.addEventListener('change', handler);
                }
            } catch (err) {
                console.warn('Failed to bind fileno input for mortgage detection', err);
            }

        }

        updateTempStatus() {
            if (this.tempStatus) {
                this.tempStatus.classList.toggle('hidden', !this.state.tempFileNumberLoading);
            }

            if (this.tempValue) {
                if (this.state.tempFileNumberLoading) {
                    this.tempValue.textContent = 'Generating...';
                } else {
                    this.tempValue.textContent = this.state.tempFileNumber || 'Pending...';
                }
            }

            if (this.tempError) {
                const hasError = normalizeText(this.state.tempFileNumberError).length > 0;
                this.tempError.classList.toggle('hidden', !hasError);
                this.tempError.textContent = hasError ? this.state.tempFileNumberError : '';
            }
        }

        async fetchTempFileNumber(force = false) {
            if (!this.tempEndpoint || this.state.tempFileNumberLoading) {
                return;
            }

            this.setState('tempFileNumberLoading', true);
            this.setState('tempFileNumberError', '', { silent: true });

            try {
                const response = await fetch(this.tempEndpoint, {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const payload = await response.json();
                if (payload && payload.status === 'success' && payload.temp_fileno) {
                    this.setState('tempFileNumber', payload.temp_fileno);
                } else {
                    throw new Error(payload && payload.message ? payload.message : 'Unable to generate a temporary file number');
                }
            } catch (error) {
                this.setState('tempFileNumberError', error.message || 'Unable to generate a temporary file number');
            } finally {
                this.setState('tempFileNumberLoading', false);
            }
        }

        bindPropertyDescriptionRefresh() {
            if (this.refreshDescriptionButton) {
                this.refreshDescriptionButton.addEventListener('click', () => this.updatePropertyDescription());
            }
        }

        bindStreetComponent() {
            if (!this.streetComponent) {
                return;
            }

            const select = this.streetComponent.querySelector('[data-role="street-select"]');
            const otherInput = this.streetComponent.querySelector('[data-role="street-other"]');
            if (!select || !otherInput) {
                return;
            }

            const originalName = select.name;

            const applyStreetState = () => {
                const value = select.value;
                if (value === 'other') {
                    select.name = '';
                    otherInput.classList.remove('hidden');
                    otherInput.name = otherInput.dataset.nameTarget || originalName;
                    this.setState('streetName', 'other');
                    this.setState('currentStreetName', otherInput.value);
                } else {
                    select.name = originalName;
                    otherInput.classList.add('hidden');
                    otherInput.name = '';
                    otherInput.value = '';
                    const display = (value && select.options[select.selectedIndex]) ? select.options[select.selectedIndex].textContent : '';
                    this.setState('streetName', value);
                    this.setState('currentStreetName', display);
                }
                this.updatePropertyDescription();
            };

            select.addEventListener('change', () => applyStreetState());
            otherInput.addEventListener('input', () => {
                this.setState('currentStreetName', otherInput.value);
                this.updatePropertyDescription();
            });

            applyStreetState();
        }

        bindDistrictComponent() {
            if (!this.districtComponent) {
                return;
            }

            const select = this.districtComponent.querySelector('[data-role="district-select"]');
            const otherInput = this.districtComponent.querySelector('[data-role="district-other"]');
            if (!select || !otherInput) {
                return;
            }

            const originalName = select.name;

            const applyDistrictState = () => {
                const value = select.value;
                if (value === 'other') {
                    select.name = '';
                    otherInput.classList.remove('hidden');
                    otherInput.name = otherInput.dataset.nameTarget || originalName;
                    this.setState('district', 'other');
                    this.setState('currentDistrict', otherInput.value);
                } else {
                    select.name = originalName;
                    otherInput.classList.add('hidden');
                    otherInput.name = '';
                    otherInput.value = '';
                    const display = (value && select.options[select.selectedIndex]) ? select.options[select.selectedIndex].textContent : '';
                    this.setState('district', value);
                    this.setState('currentDistrict', display);
                }
                this.updatePropertyDescription();
            };

            select.addEventListener('change', () => applyDistrictState());
            otherInput.addEventListener('input', () => {
                this.setState('currentDistrict', otherInput.value);
                this.updatePropertyDescription();
            });

            applyDistrictState();
        }

        bindInstrumentControls() {
            if (this.addInstrumentButton) {
                this.addInstrumentButton.addEventListener('click', () => {
                    this.addInstrumentEntry();
                });
            }

            this.renderInstruments();
        }

        addInstrumentEntry() {
            const entry = {
                id: Date.now() + Math.random(),
                type: '',
                date: '',
                party_from: '',
                party_to: ''
            };
            this.instrumentEntries.push(entry);
            this.renderInstruments();
        }

        removeInstrumentEntry(index) {
            if (index < 0 || index >= this.instrumentEntries.length) {
                return;
            }
            this.instrumentEntries.splice(index, 1);
            this.renderInstruments();
        }

        renderInstruments() {
            if (!this.instrumentList) {
                return;
            }

            this.instrumentList.innerHTML = '';

            if (!this.instrumentEntries.length) {
                if (this.instrumentEmptyMessage) {
                    this.instrumentEmptyMessage.classList.remove('hidden');
                }
                return;
            }

            if (this.instrumentEmptyMessage) {
                this.instrumentEmptyMessage.classList.add('hidden');
            }

            this.instrumentEntries.forEach((entry, index) => {
                const fragment = this.instrumentTemplate ? document.importNode(this.instrumentTemplate.content, true) : document.createDocumentFragment();
                let wrapper = fragment.querySelector ? fragment.querySelector('[data-role="instrument-entry"]') : null;

                if (!wrapper) {
                    wrapper = document.createElement('div');
                    wrapper.className = 'mt-4 rounded-lg p-4 space-y-4 border border-gray-200 bg-white';
                    fragment.appendChild(wrapper);
                }

                wrapper.dataset.index = String(index);
                this.applyInstrumentColors(wrapper, entry.type);

                const indexSpan = fragment.querySelector('[data-role="instrument-index"]');
                if (indexSpan) {
                    indexSpan.textContent = String(index + 1);
                }

                const tag = fragment.querySelector('[data-role="instrument-tag"]');
                if (tag) {
                    if (entry.type) {
                        tag.textContent = entry.type;
                        tag.classList.remove('hidden');
                    } else {
                        tag.textContent = '';
                        tag.classList.add('hidden');
                    }
                    this.applyInstrumentTagColors(tag, entry.type);
                }

                const typeSelect = fragment.querySelector('[data-field="type"]');
                if (typeSelect) {
                    typeSelect.name = `instrument_entries[${index}][type]`;
                    typeSelect.innerHTML = '';
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select type';
                    typeSelect.appendChild(defaultOption);
                    INSTRUMENT_TYPE_OPTIONS.forEach((optionValue) => {
                        const option = document.createElement('option');
                        option.value = optionValue;
                        option.textContent = optionValue;
                        if (optionValue === entry.type) {
                            option.selected = true;
                        }
                        typeSelect.appendChild(option);
                    });
                    if (entry.type && !INSTRUMENT_TYPE_OPTIONS.includes(entry.type)) {
                        const custom = document.createElement('option');
                        custom.value = entry.type;
                        custom.textContent = entry.type;
                        custom.selected = true;
                        typeSelect.appendChild(custom);
                    }
                    typeSelect.addEventListener('change', (event) => {
                        entry.type = event.target.value;
                        this.renderInstruments();
                    });
                }

                const dateInput = fragment.querySelector('[data-field="date"]');
                if (dateInput) {
                    dateInput.name = `instrument_entries[${index}][date]`;
                    dateInput.value = entry.date;
                    dateInput.addEventListener('change', (event) => {
                        entry.date = event.target.value;
                    });
                }

                const partyFromInput = fragment.querySelector('[data-field="party_from"]');
                const partyToInput = fragment.querySelector('[data-field="party_to"]');
                const labelFrom = fragment.querySelector('[data-role="instrument-label-from"]');
                const labelTo = fragment.querySelector('[data-role="instrument-label-to"]');
                const hint = fragment.querySelector('[data-role="instrument-hint"]');

                const partyLabels = INSTRUMENT_PARTY_MAP[entry.type] || { from: 'Party A', to: 'Party B' };

                if (labelFrom) {
                    labelFrom.textContent = partyLabels.from;
                }

                if (labelTo) {
                    labelTo.textContent = partyLabels.to;
                }

                if (hint) {
                    hint.classList.toggle('hidden', entry.type !== 'Subdivision');
                }

                if (partyFromInput) {
                    partyFromInput.name = `instrument_entries[${index}][party_from]`;
                    partyFromInput.value = entry.party_from;
                    partyFromInput.setAttribute('placeholder', partyLabels.from);
                    partyFromInput.addEventListener('input', (event) => {
                        entry.party_from = event.target.value;
                    });
                }

                if (partyToInput) {
                    partyToInput.name = `instrument_entries[${index}][party_to]`;
                    partyToInput.value = entry.party_to;
                    partyToInput.setAttribute('placeholder', partyLabels.to);
                    partyToInput.addEventListener('input', (event) => {
                        entry.party_to = event.target.value;
                    });
                }

                const removeButton = fragment.querySelector('[data-action="remove-instrument"]');
                if (removeButton) {
                    removeButton.addEventListener('click', () => this.removeInstrumentEntry(index));
                }

                this.instrumentList.appendChild(fragment);
            });
        }

        applyInstrumentColors(wrapper, type) {
            if (!wrapper) {
                return;
            }

            const colors = INSTRUMENT_COLOR_MAP[type] || INSTRUMENT_COLOR_MAP.default;
            const borderClasses = ALL_COLOR_CLASSES.filter((cls) => cls.startsWith('border-'));
            const bgClasses = ALL_COLOR_CLASSES.filter((cls) => cls.startsWith('bg-'));

            wrapper.classList.remove(...borderClasses, ...bgClasses);
            wrapper.classList.remove('bg-white');
            wrapper.classList.add(colors.border, colors.bg);
        }

        applyInstrumentTagColors(tag, type) {
            if (!tag) {
                return;
            }
            const colors = INSTRUMENT_COLOR_MAP[type] || INSTRUMENT_COLOR_MAP.default;
            const tagClasses = ALL_COLOR_CLASSES.filter((cls) => cls.includes('text-') || cls.startsWith('bg-'));
            tag.classList.remove(...tagClasses);
            colors.tag.split(' ').forEach((cls) => tag.classList.add(cls));
        }

        bindRegNumberSynchronisers() {
            this.serialSynchronizers.forEach((element) => {
                const targetKey = element.dataset.sync;
                if (!targetKey) {
                    return;
                }
                element.addEventListener('input', () => {
                    const targetElement = this.modelElements.get(targetKey);
                    const value = element.value || '';
                    if (targetElement) {
                        if (!targetElement.value) {
                            targetElement.value = value;
                            this.setState(targetKey, value);
                        }
                    } else {
                        this.setState(targetKey, value);
                    }
                });
            });
        }

        bindRefreshButton() {
            const refreshButton = document.getElementById('refresh-property-form');
            if (!refreshButton) {
                return;
            }

            refreshButton.addEventListener('click', () => {
                this.resetForm();
            });
        }

        bindSmartSelectorWatcher() {
            const filenoInput = document.getElementById('fileno');
            if (!filenoInput) {
                return;
            }
            filenoInput.addEventListener('change', () => {
                const value = normalizeText(filenoInput.value);
                // update state and allow onStateChange handlers to run
                this.setState('fileNumber', value);
                if (value) {
                    this.setState('hasOfficialFileNumber', true);
                }

                // Auto-prefill disabled here to allow user selection from matching records list first
                /*
                try {
                    if (typeof window.queuePraPrefillForFileNumber === 'function') {
                        window.queuePraPrefillForFileNumber([value], 'smart-selector');
                    }
                } catch (err) {
                    console.warn('Failed to queue PRA prefill for fileno', err);
                }
                */
            });
        }

        bindUpdateModeHandlers() {
            if (this.form) {
                this.form.addEventListener('submit', (event) => this.handleFormSubmit(event));
            }

            if (this.container) {
                this.container.addEventListener('pra-record-applied', (event) => this.handlePraRecordApplied(event));
                this.container.addEventListener('form-mode-changed', () => this.exitUpdateMode());
            }

            const closeButton = document.getElementById('close-property-form');
            if (closeButton) {
                closeButton.addEventListener('click', () => this.exitUpdateMode());
            }

            const cancelButton = document.getElementById('cancel-property-form');
            if (cancelButton) {
                cancelButton.addEventListener('click', () => this.exitUpdateMode());
            }
        }

        handlePraRecordApplied(event) {
            // alert suppressed in favor of matching records list selection
            return;
        }

        enterUpdateMode(record, label, forcedRecordId) {
            if (!record) {
                return;
            }

            const recordId = forcedRecordId || this.resolveRecordId(record);
            if (!recordId) {
                return;
            }

            this.state.updateRecordId = recordId;
            this.state.isUpdateMode = true;

            if (this.propertyIdInput) {
                this.propertyIdInput.value = recordId;
            }

            if (this.formActionInput) {
                this.formActionInput.value = 'update';
            }

            this.ensureMethodOverrideInput('PUT');

            if (this.form) {
                const nextAction = this.buildUpdateUrl(recordId);
                if (nextAction) {
                    this.form.setAttribute('action', nextAction);
                }
            }

            if (this.submitButton) {
                this.submitButton.textContent = 'Update';
            }

            if (this.updateBanner) {
                if (this.updateBannerLabel) {
                    this.updateBannerLabel.textContent = label || this.getRecordDisplayLabel(record) || `#${recordId}`;
                }
                this.updateBanner.classList.remove('hidden');
            }

            this.setSubmitting(false);
        }

        exitUpdateMode() {
            this.state.isUpdateMode = false;
            this.state.updateRecordId = null;
            this.pendingUpdatePrompt = false;

            const titleElement = this.dialog ? this.dialog.querySelector('#form-title') : null;
            if (titleElement) {
                delete titleElement.dataset.customTitle;
            }

            if (this.propertyIdInput) {
                this.propertyIdInput.value = '';
            }

            if (this.formActionInput) {
                this.formActionInput.value = 'add';
            }

            if (this.methodOverrideInput) {
                this.methodOverrideInput.remove();
                this.methodOverrideInput = null;
            }

            if (this.form && this.formActionOriginal) {
                this.form.setAttribute('action', this.formActionOriginal);
            }

            if (this.submitButton) {
                this.submitButton.textContent = this.submitButtonDefaultLabel;
                this.submitButton.disabled = false;
                this.submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            if (this.updateBanner) {
                this.updateBanner.classList.add('hidden');
                if (this.updateBannerLabel) {
                    this.updateBannerLabel.textContent = '';
                }
            }

            this.isSubmitting = false;
        }

        ensureMethodOverrideInput(value) {
            if (!this.form) {
                return;
            }
            if (!this.methodOverrideInput) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_method';
                this.form.appendChild(input);
                this.methodOverrideInput = input;
            }
            this.methodOverrideInput.value = value;
        }

        setSubmitting(isSubmitting, labelOverride) {
            this.isSubmitting = isSubmitting;
            if (!this.submitButton) {
                return;
            }

            if (isSubmitting) {
                this.submitButton.disabled = true;
                this.submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                if (labelOverride) {
                    this.submitButton.textContent = labelOverride;
                }
            } else {
                this.submitButton.disabled = false;
                this.submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                if (this.state.isUpdateMode) {
                    this.submitButton.textContent = 'Update';
                } else {
                    this.submitButton.textContent = this.submitButtonDefaultLabel;
                }
            }
        }

        async handleFormSubmit(event) {
            const isInstrumentsPage = window.location.pathname.toLowerCase().includes('/instruments/create');
            
            if (!this.state.isUpdateMode && !isInstrumentsPage) {
                return;
            }

            event.preventDefault();

            if (this.state.isUpdateMode && !this.state.updateRecordId) {
                this.showAlert('Missing record identifier for update.', 'error');
                return;
            }

            if (this.isSubmitting) {
                return;
            }

            const submittingMsg = this.state.isUpdateMode ? 'Updating…' : 'Saving…';
            this.setSubmitting(true, submittingMsg);

            try {
                const formData = new FormData(this.form);
                let url = this.form.action;
                
                if (this.state.isUpdateMode) {
                    formData.append('_method', 'PUT');
                    url = this.buildUpdateUrl(this.state.updateRecordId);
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await this.parseJsonSafely(response);

                if (!response.ok || !data || data.status !== 'success') {
                    let message = data && data.message ? data.message : (this.state.isUpdateMode ? 'Failed to update property record' : 'Failed to save property record');
                    if (data && data.errors) {
                        const errorMessages = Object.values(data.errors)
                            .flat()
                            .map((value) => normalizeText(value))
                            .filter((value) => value.length > 0);
                        if (errorMessages.length) {
                            message = errorMessages.join(' ');
                        }
                    }
                    this.showAlert(message, 'error');
                    return;
                }

                const successMessage = data.message || (this.state.isUpdateMode ? 'Property record updated successfully' : 'Property record saved successfully');

                if (isInstrumentsPage && !this.state.isUpdateMode) {
                    // Close the mortgage modal
                    const modal = document.getElementById('property-form-dialog');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.style.display = 'none';
                    }

                    // Show success alert
                    if (typeof window.Swal !== 'undefined') {
                        await window.Swal.fire({
                            title: 'Mortgage Captured Successfully',
                            text: 'The existing mortgage has been successfully captured. The property location details will be passed to your Deed of Surrender and Release form.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        window.alert('Mortgage Captured Successfully!');
                    }

                    // Capture all values from the mortgage form BEFORE resetting it
                    const plotNoVal = document.querySelector('#property-form-dialog #plotNo') ? document.querySelector('#property-form-dialog #plotNo').value : '';
                    const tpNoVal = document.querySelector('#property-form-dialog #tp_no_prop') ? document.querySelector('#property-form-dialog #tp_no_prop').value : '';
                    const lpknNoVal = document.querySelector('#property-form-dialog #lpkn_no_prop') ? document.querySelector('#property-form-dialog #lpkn_no_prop').value : '';
                    const plotSizeVal = document.querySelector('#property-form-dialog #plot_size_prop') ? document.querySelector('#property-form-dialog #plot_size_prop').value : '';
                    
                    // Street
                    const streetSelect = document.querySelector('#property-form-dialog #streetName');
                    let streetVal = '';
                    let isManualStreet = false;
                    if (streetSelect) {
                        streetVal = streetSelect.value;
                        if (streetVal === 'Others') {
                            isManualStreet = true;
                            const manualStreet = document.querySelector('#property-form-dialog #manual_street_name');
                            if (manualStreet) streetVal = manualStreet.value;
                        }
                    }

                    // District
                    const districtSelect = document.querySelector('#property-form-dialog #desc_district');
                    let districtVal = '';
                    let isManualDistrict = false;
                    if (districtSelect) {
                        districtVal = districtSelect.value;
                        if (districtVal === 'Others') {
                            isManualDistrict = true;
                            const manualDistrict = document.querySelector('#property-form-dialog #manual_district');
                            if (manualDistrict) districtVal = manualDistrict.value;
                        }
                    }

                    // LGA
                    const lgaSelect = document.querySelector('#property-form-dialog #desc_lga');
                    const lgaVal = lgaSelect ? lgaSelect.value : '';

                    // Property Description
                    const propDescVal = document.querySelector('#property-form-dialog #property-description') ? document.querySelector('#property-form-dialog #property-description').value : '';

                    // Extract parties from Mortgage Form before resetting
                    const firstPartyInput = document.querySelector('#property-form-dialog [data-model="firstParty"]');
                    const secondPartyInput = document.querySelector('#property-form-dialog [data-model="secondParty"]');
                    const thirdPartyInput = document.querySelector('#property-form-dialog [data-model="thirdParty"]');
                    const hasTripartiteCheckbox = document.querySelector('#property-form-dialog [data-model="tripartiteHasThird"]');

                    const mortgagorName = firstPartyInput ? firstPartyInput.value : '';
                    const mortgageeName = secondPartyInput ? secondPartyInput.value : '';
                    const coMortgagorName = thirdPartyInput ? thirdPartyInput.value : '';
                    const hasCoMortgagor = hasTripartiteCheckbox ? hasTripartiteCheckbox.checked : false;

                    // Now safely reset form submit button and state so it can be used again
                    this.resetForm();

                    // Pass property location details to the Capture Deed of Surrender / Release form!
                    const mainPlot = document.querySelector('#registration-dialog #plotNumber');
                    if (mainPlot) mainPlot.value = plotNoVal;

                    const mainTp = document.querySelector('#registration-dialog #tp_no');
                    if (mainTp) mainTp.value = tpNoVal;

                    const mainLpkn = document.querySelector('#registration-dialog #lpkn_no');
                    if (mainLpkn) mainLpkn.value = lpknNoVal;

                    const mainSize = document.querySelector('#registration-dialog #size');
                    if (mainSize) mainSize.value = plotSizeVal;

                    const mainStreet = document.querySelector('#registration-dialog #streetName');
                    const mainManualStreet = document.querySelector('#registration-dialog #manual_street_name');
                    if (mainStreet) {
                        if (isManualStreet) {
                            mainStreet.value = 'Others';
                            mainStreet.dispatchEvent(new Event('change'));
                            if (mainManualStreet) {
                                mainManualStreet.value = streetVal;
                                mainManualStreet.dispatchEvent(new Event('input'));
                            }
                        } else {
                            mainStreet.value = streetVal;
                            mainStreet.dispatchEvent(new Event('change'));
                        }
                    }

                    const mainDistrict = document.querySelector('#registration-dialog #desc_district');
                    const mainManualDistrict = document.querySelector('#registration-dialog #manual_district');
                    const surveyDistrict = document.querySelector('#registration-dialog #district');
                    if (mainDistrict) {
                        if (isManualDistrict) {
                            mainDistrict.value = 'Others';
                            mainDistrict.dispatchEvent(new Event('change'));
                            if (mainManualDistrict) {
                                mainManualDistrict.value = districtVal;
                                mainManualDistrict.dispatchEvent(new Event('input'));
                            }
                        } else {
                            mainDistrict.value = districtVal;
                            mainDistrict.dispatchEvent(new Event('change'));
                        }
                    }
                    if (surveyDistrict) {
                        surveyDistrict.value = districtVal;
                        surveyDistrict.dispatchEvent(new Event('change'));
                    }

                    const mainLga = document.querySelector('#registration-dialog #desc_lga');
                    const surveyLga = document.querySelector('#registration-dialog #lga');
                    if (mainLga) {
                        mainLga.value = lgaVal;
                        mainLga.dispatchEvent(new Event('change'));
                    }
                    if (surveyLga) {
                        surveyLga.value = lgaVal;
                        surveyLga.dispatchEvent(new Event('change'));
                    }

                    // Activate Survey Info Checkbox if LGA or District has a value
                    const surveyInfoCheckbox = document.querySelector('#registration-dialog #surveyInfo');
                    if (surveyInfoCheckbox && (lgaVal || districtVal)) {
                        surveyInfoCheckbox.checked = true;
                        surveyInfoCheckbox.dispatchEvent(new Event('change'));
                        
                        // Also show survey section
                        const surveySection = document.getElementById('survey-info-section');
                        if (surveySection) {
                            surveySection.classList.remove('hidden');
                        }
                    }

                    const mainDesc = document.querySelector('#registration-dialog #propertyDescription');
                    if (mainDesc) {
                        mainDesc.value = propDescVal;
                    }

                    // BACKFILL & SWITCH PARTIES:
                    // Mortgagee (Second Party) -> Surrenderer/Releasor (firstPartyName in capture modal)
                    // Mortgagor (First Party) -> Surrenderee/Releasee (secondPartyName in capture modal)
                    const mainFirstParty = document.querySelector('#registration-dialog #firstPartyName');
                    if (mainFirstParty) {
                        mainFirstParty.value = mortgageeName;
                        mainFirstParty.dispatchEvent(new Event('input'));
                    }

                    const mainSecondParty = document.querySelector('#registration-dialog #secondPartyName');
                    if (mainSecondParty) {
                        mainSecondParty.value = mortgagorName;
                        mainSecondParty.dispatchEvent(new Event('input'));
                    }

                    // Co-Mortgagor -> Co-Surrenderor
                    const mainHasThirdParty = document.querySelector('#registration-dialog #hasThirdParty');
                    const mainCoSurrenderor = document.querySelector('#registration-dialog #coSurrenderorName');
                    const mainThirdPartyContainer = document.querySelector('#registration-dialog #thirdPartyContainer');
                    if (hasCoMortgagor && coMortgagorName) {
                        if (mainHasThirdParty) {
                            mainHasThirdParty.checked = true;
                            mainHasThirdParty.dispatchEvent(new Event('change'));
                        }
                        if (mainThirdPartyContainer) {
                            mainThirdPartyContainer.classList.remove('hidden');
                        }
                        if (mainCoSurrenderor) {
                            mainCoSurrenderor.value = coMortgagorName;
                            mainCoSurrenderor.dispatchEvent(new Event('input'));
                        }
                    }

                    // Check if we need to call checkDuplicate to re-evaluate history and recognize the mortgage
                    if (typeof window.checkDuplicate === 'function') {
                        window.checkDuplicate();
                    }

                } else {
                    if (typeof window.Swal !== 'undefined') {
                        await window.Swal.fire({
                            title: this.state.isUpdateMode ? 'Updated' : 'Saved',
                            text: successMessage,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        window.alert(successMessage);
                    }

                    this.exitUpdateMode();
                    window.location.reload();
                }
            } catch (error) {
                const message = error && error.message ? error.message : (this.state.isUpdateMode ? 'Failed to update property record' : 'Failed to save property record');
                this.showAlert(message, 'error');
            } finally {
                this.setSubmitting(false);
            }
        }

        buildUpdateUrl(recordId) {
            const targetId = encodeURIComponent(recordId);
            if (this.updateBaseUrl) {
                const trimmed = this.updateBaseUrl.replace(/\/$/, '');
                return `${trimmed}/${targetId}`;
            }
            if (this.formActionOriginal) {
                const trimmed = this.formActionOriginal.replace(/\/$/, '');
                return `${trimmed}/${targetId}`;
            }
            return '';
        }

        getRecordDisplayLabel(record) {
            const candidates = [
                record.mlsFNo,
                record.mlsfno,
                record.kangisFileNo,
                record.NewKANGISFileno,
                record.fileno,
                record.file_number,
                record.temp_fileno,
                record.prop_id,
                record.property_id,
                record.propertyId,
                record.id ? `#${record.id}` : ''
            ];

            for (const candidate of candidates) {
                const normalized = normalizeText(candidate);
                if (normalized) {
                    return normalized;
                }
            }
            return '';
        }

        resolveRecordId(record) {
            if (!record) {
                return null;
            }

            const candidates = [
                record.id,
                record.prop_id,
                record.property_id,
                record.propertyId,
                record.record_id,
                record.recordId
            ];

            for (const candidate of candidates) {
                if (candidate !== undefined && candidate !== null && `${candidate}`.length) {
                    return candidate;
                }
            }

            return null;
        }

        async parseJsonSafely(response) {
            try {
                return await response.clone().json();
            } catch (error) {
                return null;
            }
        }

        showAlert(message, type) {
            const text = message || 'An unexpected error occurred.';
            if (typeof window.Swal !== 'undefined') {
                window.Swal.fire({
                    title: type === 'error' ? 'Update Failed' : 'Notice',
                    text,
                    icon: type === 'error' ? 'error' : 'info'
                });
            } else {
                window.alert(text);
            }
        }

        resolveCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && meta.getAttribute('content')) {
                return meta.getAttribute('content');
            }
            const tokenInput = this.form ? this.form.querySelector('input[name="_token"]') : null;
            return tokenInput ? tokenInput.value : '';
        }
    }

    function initialisePraController() {
        const container = document.querySelector('.pra-property-form[data-pra-form]');
        if (!container) {
            return;
        }

        const controller = new PraFormController(container);
        controller.init();

        window.PraFormController = {
            container,
            controller,
            getPublicApi() {
                return controller.publicApi;
            },
            setFormMode(mode) {
                controller.setState('formMode', mode);
            },
            refreshDescription() {
                controller.updatePropertyDescription();
            },
            resetForm() {
                controller.resetForm();
            },
            resetUpdateMode() {
                controller.exitUpdateMode();
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialisePraController);
    } else {
        initialisePraController();
    }
})(window, document);
