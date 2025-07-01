App.Modules.Auth.Components.LoginForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.LoginForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-login-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form); 
   
        this._loginButton = this.Children('button-container/login');
        this._registerButton = this.Children('button-container2/register');
        this._resetButton = this.Children('links-container/reset');
        this._buttonContainerFingerprint = this.Children('button-container/fingerprint');
        

        this._timerContainer = this.Children('timer-container');
        this._timer = this.Children('timer-container/timer');
        this._timerTemplate = this._timer.value;
        this._requestCode = this.Children('timer-container/request-code-again');

        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);
        
        this._registerButton.AddHandler('Clicked', this.__registerButtonClicked, false, this);
        this._resetButton.AddHandler('Clicked', this.__resetButtonClicked, false, this);
        this._loginButton.AddHandler('Clicked', this.__loginFormLoginButtonClicked, false, this);
        this._requestCode.AddHandler('Clicked', this.__requestCodeAgainClicked, false, this);
        this._buttonContainerFingerprint.AddHandler('Clicked', this.__buttonContainerFingerprintClicked, false, this);

        Auth.App.Settings().then((settings) => {
            this._buttonContainerFingerprint.shown = settings.params.enable_device_authentification;
            if(settings.params.enable_device_authentification) {
                this.__buttonContainerFingerprintClicked(null, null);
            }
        });

    } 

    __resetButtonClicked(event, args) {
        return this.Dispatch('ResetButtonClicked', args);
    }

    __registerButtonClicked(event, args) {
        return this.Dispatch('RegisterButtonClicked', args);
    }

    __buttonContainerFingerprintClicked(event, args) {
        App.Device.Auth.IsAvailable().then(() => {
            App.Device.Auth.Authenticate().then((credentials) => {
                this.LoginByCreds(credentials);
            });
        }).catch(() => {
            this._buttonContainerFingerprint.shown = false;
        });
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __requestCodeAgainClicked(event, args) {
        this._form.enabled = false;
        this.Login(true);
    }

    /** @protected */
    _registerEvents() {
        this.RegisterEvent('RegisterButtonClicked', true, 'Когда нажата кнопка регистрации');
        this.RegisterEvent('ResetButtonClicked', true, 'Когда нажата кнопка восстановления пароля');
    }

    /**
     * Shows the component
     * @type {boolean}
     */
    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __loginFormLoginButtonClicked(event, args) {

        if(!this._validator.ValidateAll()) {

            const component = this._form.container.querySelector('.app-validate-error').tag('component');
            if(component) {
                component.Focus();
            }

            return;
        }

        this.Login();

    }

    _startTimer() {
        this._timerContainer.shown = true;
        this._timer.shown = true;
        this._requestCode.shown = false;
        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);
        Colibri.Common.StartTimer('request-code-timer', 1000, () => {
            if(this._timeLeft <= 2) {
                Colibri.Common.StopTimer('request-code-timer');
                this._timer.shown = false;
                this._requestCode.shown = true;
                return;
            }
            this._timeLeft --;
            this._timer.shown = true;
            this._requestCode.shown = false;
            this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);
        });
    }

    Login(rerequestCode = false) {
        const value = Object.cloneRecursive(this._form.value);
        this._form.enabled = false;
        this._loginButton.enabled = false;
        Auth.Session.Login(value.login, value.password, !rerequestCode ? (value.code ?? null) : null).then((sessionAndCode) => {
            let session = sessionAndCode;
            let code = 206;
            if(sessionAndCode?.code) {
                session = sessionAndCode.session;
                code = sessionAndCode.code;
            }
            this._form.enabled = true;
            this._loginButton.enabled = true;

            if(!session && code === 206) { // 2-х факторка
                const fields = Object.cloneRecursive(this._form.fields);
                fields.login.params.readonly = true;
                fields.password.params.readonly = true;
                fields.code.params.hidden = false;
                this._form.fields = fields;
                this._form.value = value;
                this._form.Children('code').Focus();

                this._confirming = false;
                this._startTimer();
            } else if(!session && code === 207) {
                const fields = Object.cloneRecursive(this._form.fields);
                fields.login.params.readonly = true;
                fields.password.params.readonly = true;
                fields.code.params.hidden = false;
                fields.code.desc = '#{auth-loginform-appcode-desc}';
                this._form.fields = fields;
                this._form.value = value;
                this._form.Children('code').Focus();

                this._confirming = false;
                this._startTimer();
            }

        }).catch(response => {
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            this._validator.Invalidate('form', response.result.message);
            this._form.enabled = true;
            this._loginButton.enabled = true;
            this._form.Focus();
        });
    }
    
    LoginByCreds(credentials) {
        const value = Object.cloneRecursive(this._form.value);
        this._form.enabled = false;
        this._loginButton.enabled = false;
        Auth.Session.LoginByCreds(credentials).then((session) => {
            this._form.enabled = true;
            this._loginButton.enabled = true;
        }).catch(response => {
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            this._validator.Invalidate('form', response.result.message);
            this._form.enabled = true;
            this._loginButton.enabled = true;
            this._form.Focus();
        });
    }

}