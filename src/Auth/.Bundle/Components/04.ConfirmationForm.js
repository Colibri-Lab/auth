App.Modules.Auth.Components.ConfirmationForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ConfirmationForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-confirmation-form-component'); 
        this._property = 'none';

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);

        this._timer = this.Children('timer-container/timer');
        this._timerTemplate = this._timer.value;
        this._requestCode = this.Children('timer-container/request-code-again');

        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);

        this._form.AddHandler('Changed', (event, args) => {
            if(this._validator.ValidateAll()) {

                if(this._confirming) {
                    return;
                }
    
                this._confirming = true;
                this.__confirmationFormConfirmationButtonClicked(event, args);
                
            }
        });

        this._requestCode.AddHandler('Clicked', (event, args) => this.__requestCodeAgainClicked(event, args));

    } 

    _registerEvents() {
        this.RegisterEvent('PropertyConfirmed', true, 'Сойвство подтверждено');
    }

    /**
     * Sets the confirming property
     * @type {string}
     */
    set property(value) {
        this._property = value;
    }

    get property() {
        return this._property;
    }

    /**
     * Shows the component
     * @type {boolean}
     */
    set shown(value) {
        super.shown = value;
        this._form.Children('code').Focus();
    }

    /**
     * Value object
     * @type {Object}
     */
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

            Auth.Members.ConfirmProperty(this._form.value.code, this._property).then((session) => {
                this.Dispatch('PropertyConfirmed', {property: this._property});
                this._confirming = false;
            }).catch(response => {
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
                this._confirming = false;
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
        this.RequestCode();

    }

    RequestCode() {
        this._form.enabled = false;
        Auth.Members.BeginConfirmationProcess(this._property).then((session) => {
            this._form.enabled = true;
            this._confirming = false;
            this._startTimer();
        }).catch(response => {
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
            this._confirming = false;
        }); 
    }

}