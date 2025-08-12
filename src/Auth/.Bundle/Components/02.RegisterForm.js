App.Modules.Auth.Components.RegisterForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.RegisterForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-register-form-component'); 

        this._step1 = this.Children('form-container/step1');
        this._step2 = this.Children('form-container/step2');
        this._step3 = this.Children('form-container/step3');
        
        this._form = this.Children('form-container/step3/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);
        
        this._loginButton = this.Children('button-container2/login');
        this._registerButton = this.Children('form-container/step3/button-container/register');
        
        this._loginButton.AddHandler('Clicked', (event, args) => this.Dispatch('LoginButtonClicked', args));
        this._registerButton.AddHandler('Clicked', this.__registerFormRegisterButtonClicked, false, this);
        
        this._step1.AddHandler('Changed', (event, args) => this.Dispatch('ExternalValidation', args));
        this._step2.AddHandler('Changed', (event, args) => this.Dispatch('ExternalValidation', args));
        
        this._step1.AddHandler('PropertyConfirmed', this.__step1PropertyConfirmed, false, this);
        this._step2.AddHandler('PropertyConfirmed', this.__step2PropertyConfirmed, false, this);
        
        
        Auth.App.Settings().then((settings) => {
            
            if(settings.params.askforphone) {                
                this._registrationData = {
                    phone: null, 
                    phone_confirmed: false,
                    email: null,
                    email_confirmed: false,
                };
                this._renderFields(true);
                this._currentStep = 1;
            } else {
                this._registrationData = {
                    email: null,
                    email_confirmed: false,
                };
                this._renderFields(false);
                this._currentStep = 2;
            }
            this._showStep();
        });

    } 

    _renderFields(withPhone = true) {
        if(withPhone) {
            this._form.fields = {
                email_confirmed: {
                    component: 'Checkbox',
                    hidden: true,
                },
                phone_confirmed: {
                    component: 'Checkbox',
                    hidden: true
                },
                email: {
                    component: 'Text',
                    desc: '#{auth-registerform-email-desc}',
                    params: {
                        required: true,
                        readonly: false,
                        enabled: false,
                        icon: App.Modules.Auth.Icons.Done,
                        className: '-icon-right -icon-green'
                    },
                    attrs: {
                        width: '100%'
                    } 
                },
                phone: {
                    component: 'Text',
                    desc: '#{auth-registerform-phone-desc}',
                    params: {
                        required: true,
                        readonly: false,
                        enabled: false,
                        icon: App.Modules.Auth.Icons.Done,
                        className: '-icon-right -icon-green'
                    },
                    attrs: {
                        width: '100%'
                    }
                },
                pass: {
                    component: 'Object',
                    desc: '#{auth-registerform-pass-desc}',
                    params: {
                        vertical: true
                    },
                    fields: {
                        password: {
                            component: 'Password',
                            desc: '#{auth-registerform-password-placeholder}',
                            /* desc: '#{auth-registerform-password-desc}', */
                            params: {
                                required: true,
                                readonly: false,
                                eyeicon: true,
                                tip: {
                                    orientation: [Colibri.UI.ToolTip.RT, Colibri.UI.ToolTip.LT],
                                    className: 'app-password-tip-component',
                                    text: ['#{auth-registerform-password-tip-text}', '#{auth-registerform-password-tip-text2}'],
                                    success: '#{auth-registerform-password-tip-success}',
                                    error: '#{auth-registerform-password-tip-error}',
                                    generate: '#{auth-registerform-password-tip-generate}',
                                    copied: '#{auth-registerform-password-tip-copied}',
                                    digits: '#{auth-registerform-password-tip-digits}',
                                    additional: [
                                        '#{auth-registerform-password-tip-additional-1}', 
                                        '#{auth-registerform-password-tip-additional-2}',
                                        '#{auth-registerform-password-tip-additional-3}', 
                                        '#{auth-registerform-password-tip-additional-4}'
                                    ]
                                },
                                requirements: {
                                    digits: 8,
                                    strength: 70,
                                    minForStrong: 90,
                                    minForGood: 70,
                                    minForWeak: 50
                                },
                                validate: [
                                    {
                                        message: '#{auth-registerform-password-validation1}',
                                        method: '(field, validator) => !!field.value'
                                    },
                                    {
                                        message: '#{auth-registerform-password-validation3}',
                                        method: '(field, validator) => validator.form.FindField("pass/password").CalcPasswordStrength() > 60'
                                    }
                                ]
                            },
                            attrs: {
                                width: '100%'
                            }
                        },
                        confirmation: {
                            component: 'Password',
                            desc: '#{auth-registerform-confirmation-placeholder}',
                            /* desc: '#{auth-registerform-confirmation-desc}', */
                            params: {
                                required: true,
                                readonly: false,
                                eyeicon: true,
                                validate: [
                                    {
                                        message: '#{auth-registerform-password-validation2}',
                                        method: '(field, validator) => !!field.value'
                                    },
                                    {
                                        message: '#{auth-registerform-confirmation-validation4}',
                                        method: '(field, validator) => field.value == validator.form.value.pass.password'
                                    }
                                ]                  
                            },
                            attrs: {
                                width: '100%'
                            }
                        } 
                    }
                },
                aggreenment: {
                    component: 'Checkbox',
                    placeholder: '#{auth-registerform-aggreenment-placeholder}',
                    params: {
                        required: true,
                        readonly: false,
                        validate: [
                            {
                                message: '#{auth-registerform-aggreenment-validation1}',
                                method: '(field, validator) => !!field.value'
                            }
                        ]              
                    },
                    attrs: {
                        width: '100%'
                    }
                }
            };
        } else {
            this._form.fields = {
                email_confirmed: {
                    component: 'Checkbox',
                    hidden: true,
                },
                email: {
                    component: 'Text',
                    desc: '#{auth-registerform-email-desc}',
                    params: {
                        required: true,
                        readonly: false,
                        enabled: false,
                        icon: App.Modules.Auth.Icons.Done,
                        className: '-icon-right -icon-green'
                    },
                    attrs: {
                        width: '100%'
                    } 
                },
                pass: {
                    component: 'Object',
                    desc: '#{auth-registerform-pass-desc}',
                    params: {
                        vertical: true
                    },
                    fields: {
                        password: {
                            component: 'Password',
                            desc: '#{auth-registerform-password-placeholder}',
                            /* desc: '#{auth-registerform-password-desc}', */
                            params: {
                                required: true,
                                readonly: false,
                                eyeicon: true,
                                tip: {
                                    orientation: [Colibri.UI.ToolTip.RT, Colibri.UI.ToolTip.LT],
                                    className: 'app-password-tip-component',
                                    text: ['#{auth-registerform-password-tip-text}', '#{auth-registerform-password-tip-text2}'],
                                    success: '#{auth-registerform-password-tip-success}',
                                    error: '#{auth-registerform-password-tip-error}',
                                    generate: '#{auth-registerform-password-tip-generate}',
                                    copied: '#{auth-registerform-password-tip-copied}',
                                    digits: '#{auth-registerform-password-tip-digits}',
                                    additional: [
                                        '#{auth-registerform-password-tip-additional-1}', 
                                        '#{auth-registerform-password-tip-additional-2}',
                                        '#{auth-registerform-password-tip-additional-3}', 
                                        '#{auth-registerform-password-tip-additional-4}'
                                    ]
                                },
                                requirements: {
                                    digits: 8,
                                    strength: 70,
                                    minForStrong: 90,
                                    minForGood: 70,
                                    minForWeak: 50
                                },
                                validate: [
                                    {
                                        message: '#{auth-registerform-password-validation1}',
                                        method: '(field, validator) => !!field.value'
                                    },
                                    {
                                        message: '#{auth-registerform-password-validation3}',
                                        method: '(field, validator) => validator.form.FindField("pass/password").CalcPasswordStrength() > 60'
                                    }
                                ]
                            },
                            attrs: {
                                width: '100%'
                            }
                        },
                        confirmation: {
                            component: 'Password',
                            desc: '#{auth-registerform-confirmation-placeholder}',
                            /* desc: '#{auth-registerform-confirmation-desc}', */
                            params: {
                                required: true,
                                readonly: false,
                                eyeicon: true,
                                validate: [
                                    {
                                        message: '#{auth-registerform-password-validation2}',
                                        method: '(field, validator) => !!field.value'
                                    },
                                    {
                                        message: '#{auth-registerform-confirmation-validation4}',
                                        method: '(field, validator) => field.value == validator.form.value.pass.password'
                                    }
                                ]                  
                            },
                            attrs: {
                                width: '100%'
                            }
                        } 
                    }
                },
                aggreenment: {
                    component: 'Checkbox',
                    placeholder: '#{auth-registerform-aggreenment-placeholder}',
                    params: {
                        required: true,
                        readonly: false,
                        validate: [
                            {
                                message: '#{auth-registerform-aggreenment-validation1}',
                                method: '(field, validator) => !!field.value'
                            }
                        ]              
                    },
                    attrs: {
                        width: '100%'
                    }
                }
            };
        }
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __step1PropertyConfirmed(event, args) {
        this._registrationData.phone = args.value;
        this._registrationData.phone_confirmed = true;
        if(this._invitation) {
            this._currentStep = 3;
            this._registrationData.email = this._invitation.email;
            this._registrationData.email_confirmed = true;
        } else {
            this._currentStep = 2;
        }
        this._showStep();
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object 
     * @param {*} args event arguments
     */ 
    __step2PropertyConfirmed(event, args) {
        this._registrationData.email = args.value;
        this._registrationData.email_confirmed = true;
        this._currentStep = 3;
        this._step3.Children('form').value = this._registrationData;
        this._showStep();
    }

    _showStep() {
        this._step1.shown = this._step2.shown = this._step3.shown = false;
        this['_step' + this._currentStep].shown = true;
        if(this._currentStep === 3) {
            this._form.value = this._registrationData;
        }
    }

    /** @protected */
    _registerEvents() {
        this.RegisterEvent('LoginButtonClicked', true, 'When login button is clicked');
        this.RegisterEvent('ExternalValidation', true, 'When external validation is needed');
        this.RegisterEvent('Completed', true, 'When registration is completed');
    }

    Show(invitation = null) {
        if(invitation) {
            Auth.Members.GetInvite(invitation).then(invite => {
                this._invitation = invite;
            }).finally(() => {
                super.shown = true;
            });
        } else {
            super.shown = true;
        }
    }

    /**
     * Shows the component
     * @type {boolean}
     */
    set shown(value) {
        super.shown = value;
        this._showStep();
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __registerFormRegisterButtonClicked(event, args) {
        if(!this._validator.ValidateAll()) {

            const component = this._form.container.querySelector('.app-validate-error').tag('component');
            if(component) {
                component.Focus();
            }

            return;
        }
 
        const formData = this._form.value;
        this._form.enabled = false;
        this._registerButton.enabled = false;
        Auth.Members.Register(
            formData.email, 
            formData.email_confirmed, 
            formData.phone ? formData.phone.replaceAll(/[^[0-9+]/, '') : null, 
            formData.phone_confirmed ?? false, 
            formData.pass.password, 
            formData.pass.confirmation,
            null, null, null, null, null,
            App.Router.options?.invitation ?? null
        ).then((session) => {
            this._form.enabled = false;
            this._registerButton.enabled = false;        
            this.Dispatch('Completed', session);
        }).catch(response => {
            this._form.enabled = true;
            this._registerButton.enabled = true;
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
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

    /**
     * Messages for register form
     * @type {Object}
     */
    get messages() {
        return {
            phone: {
                message1: this._step1.message1,
                message2: this._step1.message2,
            },
            email: {
                message1: this._step2.message1,
                message2: this._step2.message2,
            }
        }
    }
    /**
     * Messages for register form
     * @type {Object}
     */
    set messages(value) {
        this._step1.message1 = value.phone.message1;
        this._step1.message2 = value.phone.message2;
        this._step2.message1 = value.email.message1;
        this._step2.message2 = value.email.message2;
    }

}