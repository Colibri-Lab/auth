App.Modules.Auth.Components.ResetForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ResetForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-reset-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);

        this._form.fields = {
            email: {
                component: 'Text',
                desc: '#{auth-resetform-email-desc}',
                params: {
                    required: true,
                    readonly: false,
                    validate: [
                        {
                            message: '#{auth-resetform-email-validation1}',
                            method: '(field, validator) => !!field.value'
                        },
                        {
                            message: '#{auth-resetform-email-validation2}',
                            method: '(field, validator) => field.value.isEmail()'
                        }
                    ]
                },
                attrs: {
                    width: 480
                }
            },
            phone: {
                component: 'Text',
                desc: '#{auth-resetform-phone-desc}',
                params: {
                    required: true,
                    readonly: false,
                    validate: [
                        {
                            message: '#{auth-resetform-phone-validation1}',
                            method: '(field, validator) => !!field.value'
                        }
                    ]
                },
                attrs: {
                    width: 480
                }
            }
        };

        this._resetButton = this.Children('button-container/reset');
        this._loginButton = this.Children('button-container/login');

        this._form.AddHandler('Changed', (event, args) => {
            this._resetButton.enabled = this._validator.Status();
        });

        
        this._loginButton.AddHandler('Clicked', (event, args) => this.Dispatch('LoginButtonClicked', args));
        this._resetButton.AddHandler('Clicked', (event, args) => this.__resetFormResetButtonClicked(event, args));

    } 

    _registerEvents() {
        this.RegisterEvent('LoginButtonClicked', true, 'Когда нажата кнопка входа');
    }

    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }

    _showCodeAndPasswordFields() {
        const value = this._form.value;
        this._form.fields = {
            email: {
                component: 'Text',
                desc: '#{auth-resetform-email-desc}',
                params: {
                    required: true,
                    readonly: true,
                    validate: [
                        {
                            message: '#{auth-resetform-email-validation1}',
                            method: '(field, validator) => !!field.value'
                        },
                        {
                            message: '#{auth-resetform-email-validation2}',
                            method: '(field, validator) => field.value.isEmail()'
                        }
                    ]
                },
                attrs: {
                    width: 480
                }
            },
            phone: {
                component: 'Text',
                desc: '#{auth-resetform-phone-desc}',
                params: {
                    required: true,
                    readonly: true,
                    mask: 'SSSSSSSSSSS',
                    validate: [
                        {
                            message: '#{auth-resetform-phone-validation1}',
                            method: '(field, validator) => !!field.value'
                        }
                    ]
                },
                attrs: {
                    width: 480
                }
            },
            code: {
                component: 'Text',
                desc: '#{auth-resetform-code-desc}',
                params: {
                    required: true,
                    readonly: false,
                    validate: [
                        {
                            message: '#{auth-confirmationform-code-validation1}',
                            method: '(field, validator) => !!field.value && field.value.length === 6'
                        }
                    ],
                },
                attrs: {
                    width: 480
                }
            },
            pass: {
                component: 'Object',
                desc: '#{auth-resetform-pass-desc}',
                params: {
                    vertical: true,
                },
                fields: {
                    password: {
                        component: 'Password',
                        desc: '#{auth-resetform-password-placeholder}',
                        /* desc: '#{auth-resetform-password-desc}', */
                        params: {
                            required: true,
                            readonly: false,
                            tip: {
                                orientation: [Colibri.UI.ToolTip.LT, Colibri.UI.ToolTip.LB],
                                text: '#{auth-resetform-password-tip-text}',
                                success: '#{auth-resetform-password-tip-success}',
                                error: '#{auth-resetform-password-tip-error}',
                                generate: '#{auth-resetform-password-tip-generate}',
                                copied: '#{auth-resetform-password-tip-copied}',
                                digits: ['#{auth-resetform-password-tip-digits-1}','#{auth-resetform-password-tip-digits-2}','#{auth-resetform-password-tip-digits-3}'],
                                additional: [
                                    '#{auth-resetform-password-tip-additional-1}', 
                                    '#{auth-resetform-password-tip-additional-2}',
                                    '#{auth-resetform-password-tip-additional-3}', 
                                    '#{auth-resetform-password-tip-additional-4}'
                                ]
                            },
                            requirements: {
                                digits: 8,
                                strength: 60
                            },
                            validate: [
                                {
                                    message: '#{auth-resetform-password-validation1}',
                                    method: '(field, validator) => !!field.value'
                                },
                                {
                                    message: '#{auth-resetform-password-validation3}',
                                    method: '(field, validator) => validator.form.FindField("pass/password").CalcPasswordStrength() > 60'
                                }
                            ]
                        },
                        attrs: {
                            width: 480
                        }
                    },
                    confirmation: {
                        component: 'Password',
                        desc: '#{auth-resetform-confirmation-placeholder}',
                        params: {
                            required: true,
                            readonly: false,
                            validate: [
                                {
                                    message: '#{auth-resetform-password-validation2}',
                                    method: '(field, validator) => !!field.value'
                                },
                                {
                                    message: '#{auth-resetform-confirmation-validation4}',
                                    method: '(field, validator) => field.value == validator.form.value.pass.password'
                                }
                            ]                  
                        },
                        attrs: {
                            width: 480
                        }
                    } 
                }
            },
        };
        this._form.value = value;
    }

    __resetFormResetButtonClicked(event, args) {
        
        if(this._form.value.code) {

            Auth.Members.ResetPassword(this._form.value.email, this._form.value.phone, this._form.value.code, this._form.value.pass.password, this._form.value.pass.confirmation).then((session) => {
                this._loginButton.Dispatch('Clicked');
            }).catch(response => {
                response.result = JSON.parse(response.result);
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
        else {

            Auth.Members.BeginPasswordResetProcess(this._form.value.email, this._form.value.phone).then((session) => {
                this._showCodeAndPasswordFields();
            }).catch(response => {
                response.result = JSON.parse(response.result);
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

    }

}