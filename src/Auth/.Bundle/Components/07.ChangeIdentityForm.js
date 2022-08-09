App.Modules.Auth.Components.ChangeIdentityForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ChangeIdentityForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-confirmation-form-component'); 
        this._property = 'none';

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.FormValidator(this._form);

        this._timer = this.Children('timer-container/timer');
        this._timerTemplate = this._timer.value;
        this._requestCode = this.Children('timer-container/request-code-again');

        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);

        this._validator.AddHandler('Validated', (event, args) => {
            if(this._validator.Validate(true, false) && !this._confirming) {
                this._confirming = true;
                this.__confirmationFormConfirmationButtonClicked(event, args);
            }
        });

        this._requestCode.AddHandler('Clicked', (event, args) => this.__requestCodeAgainClicked(event, args));

    } 

    _registerEvents() {
        this.RegisterEvent('PropertyConfirmed', true, 'Сойвство подтверждено');
    }

    set property(value) {
        this._property = value;
    }

    get property() {
        return this._property;
    }

    set shown(value) {
        super.shown = value;
        this._form.Children('code').Focus();
    }

    set value(value) {
        this._form.value = value;
    }

    get value() {
        return this._form.value;
    }

    __requestCodeAgainClicked(event, args) {
        this._form.enabled = false;
        this.RequestCode();
    }

    __confirmationFormConfirmationButtonClicked(event, args) {

        if(this._form.value.code) {

            Auth.Members.ChangeIdentity(this._form.value.code, this._property).then((session) => {
                this._confirming = false;
                this.Dispatch('PropertyConfirmed', {property: this._property});
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
                    this._validator.Invalidate('code', response.result.message);
                    this._form.Children('code').Focus();
                }
            });    

        }

    }

    _startTimer() {
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

    Show(property) {
        if(property) {
            this._property = property;
        }
        super.Show();
        this._form.enabled = false;
        this.RequestCode();

    }

    RequestCode() {
        Auth.Members.BeginIdentityUpdateProcess(this._property).then((session) => {
            this._form.enabled = true;
            this._startTimer();
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
                this._validator.Invalidate('email', response.result.message);
                this._form.Children('email').Focus();
            }
        }); 
    }

}