App.Modules.Auth.Components.RegisterForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.RegisterForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-register-form-component'); 

        this._step1 = this.Children('form-container/step1');
        this._step2 = this.Children('form-container/step2');
        this._step3 = this.Children('form-container/step3');

        this._currentStep = 1;

        this._showStep();

        this._form = this.Children('form-container/step3/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);

        this._loginButton = this.Children('button-container2/login');
        this._registerButton = this.Children('form-container/step3/button-container/register');

        this._loginButton.AddHandler('Clicked', (event, args) => this.Dispatch('LoginButtonClicked', args));
        this._registerButton.AddHandler('Clicked', (event, args) => this.__registerFormRegisterButtonClicked(event, args));

        this._step1.AddHandler('Changed', (event, args) => this.Dispatch('ExternalValidation', args));
        this._step2.AddHandler('Changed', (event, args) => this.Dispatch('ExternalValidation', args));

        this._step1.AddHandler('PropertyConfirmed', (event, args) => this.__step1PropertyConfirmed(event, args));
        this._step2.AddHandler('PropertyConfirmed', (event, args) => this.__step2PropertyConfirmed(event, args));

        this._registrationData = {
            phone: null, 
            phone_confirmed: false,
            email: null,
            email_confirmed: false,
        };

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
            formData.phone.replaceAll(/[^[0-9+]/, ''), 
            formData.phone_confirmed, 
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