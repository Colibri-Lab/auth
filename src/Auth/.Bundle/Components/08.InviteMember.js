/**
 * Invite member
 * @class
 * @extends Colibri.UI.Component
 * @memberof App.Modules.Auth.Components
 */
App.Modules.Auth.Components.InviteMember = class extends Colibri.UI.Component {
    
    /**
     * @constructor
     * @param {string} name name of component
     * @param {Element|Colibri.UI.component} container container of component
     */
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.InviteMember']);
        this.AddClass('app-modules-auth-components-invitemember');
        this.AddClass('app-auth-form-component'); 

        this._form = this.Children('form-container/form'); 
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);
        this.multiple = false;

        this._saveButton = this.Children('button-container/save');
        this._saveButton.AddHandler('Clicked', (event, args) => this.__saveButtonClicked(event, args)); 

    }

    __saveButtonClicked(event, args) {

        if(!this._validator.ValidateAll()) {

            const component = this._form.container.querySelector('.app-validate-error').tag('component');
            if(component) {
                component.Focus();
            }

            return;
        }

        let rows = [];
        if(this._form.value.rows) {
            let crows = this._form.value.rows.trimString(/[\n\r\t\s]/).split(/\n/).filter(v => !!v);
            crows.forEach(v => {
                const parts = v.split(/[\s\t]/);
                const email = parts[0].trimString();
                const fio = parts.slice(1).join(' ').trimString();
                rows.push({email, fio});
            });
        } else {
            rows.push({email: this._form.value.email, fio: this._form.value.fio});
        }

        let promises = [];
        for(const o of rows) {
            promises.push(Auth.Members.Invite(o.email, o.fio, this._form.value.params));
        }

        Promise.all(promises).then(responses => {
            this.Dispatch('Completed', responses);
            this.Hide();
        }).catch(response => {
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    if(['password', 'confirmation'].indexOf(field) !== -1) {
                        field = 'pass';
                    }
                    this._validator.Invalidate(field, message);
                    if(index === 0) {
                        this._form.FindField(field).Focus();
                    }
                });
            }
            else {
                this._validator.Invalidate('form', response.result.message);
                this._form.Focus();
            }
        });

    }

    /** @protected */
    _registerEvents() {
        super._registerEvents();
        this.RegisterEvent('Completed', true, 'When invitation is sent');
    }

    /**
     * Value Object
     * @type {Object}
     */
    get value() {
        return this._value;
    }
    /**
     * Value Object
     * @type {Object}
     */
    set value(value) {
        this._value = value;
        this._showValue();
    }
    _showValue() {
        
        this._form.value = this._value;

    }

    /**
     * Show multiple invite form
     * @type {Boolean}
     */
    get multiple() {
        return this._multiple;
    }
    /**
     * Show multiple invite form
     * @type {Boolean}
     */
    set multiple(value) {
        this._multiple = value;
        this._showMultiple();
    }
    _showMultiple() {
        this._form.fields = this._multiple ? {
            rows: {
                component: 'TextArea',
                desc: '#{auth-inviteform-multiple}',
                note: '#{auth-inviteform-multiple-note}',
                attrs: {
                    inputHeight: 300,
                    inputWidth: 550,
                    width: 550,
                },
                params: {
                    validate: [
                        {
                            message: '#{auth-inviteform-rows-validation1}', 
                            method: (field, validator) => !!field.value
                        },
                        {
                            message: '#{auth-inviteform-rows-validation2}', 
                            method: (field, validator) => {
                                const rows = field.value.trimString(/[\n\r\t\s]/).split(/\n/).filter(v => !!v);
                                return rows.map(v => {
                                    const email = v.split(/[\s\t]/)[0].trimString();
                                    return email.isEmail() ? 1 : 0;
                                }).sum() === rows.length
                            }
                        }
                    ],
                },
            }
        } : {
            email: {
                component: 'Email',
                desc: '#{auth-inviteform-email}',
                params: {
                    validate: [
                        {
                            message: '#{auth-inviteform-email-validation1}', 
                            method: (field, validator) => !field.value || field.value.isEmail()
                        }
                    ],
                },
                attrs: {
                    width: '350'
                }
            },
            fio: {
                component: 'Text',
                desc: '#{auth-inviteform-fio}',
                attrs: {
                    width: '250'
                }
            }
        };
    }

}