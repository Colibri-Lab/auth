App.Modules.Auth.Components.ChangeIdentityForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ChangeIdentityForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-confirmation-form-component'); 
        this._property = 'none';
        this._changing = false;

        this._form1 = this.Children('form-container/form1');
        this._validator1 = new Colibri.UI.FormValidator(this._form1);
        this._form2 = this.Children('form-container/form2');
        this._validator2 = new Colibri.UI.FormValidator(this._form2);
        this._send = this.Children('timer-container/send');

        this._timer = this.Children('timer-container/timer');
        this._timerTemplate = this._timer.value;
        this._requestCode = this.Children('timer-container/request-code-again');

        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);

        this._validator1.AddHandler('Validated', (event, args) => {
            this._send.enabled = this._validator1.Validate(true, false);
        });
        this._validator2.AddHandler('Validated', (event, args) => {
            if(this._changing) {
                return;
            }
            this._changing = true;
            if(this._validator2.Validate(true, false)) {
                Colibri.Common.Delay(100).then(() => this.__changeFormChangeButtonClicked(event, args));
            }
        });

        this._send.AddHandler('Clicked', (event, args) => this.__sendButtonClicked(event, args));
        this._requestCode.AddHandler('Clicked', (event, args) => this.__requestCodeAgainClicked(event, args));

    } 

    _registerEvents() {
        this.RegisterEvent('PropertyChanged', true, 'Сойвство изменено');
    }

    set property(value) {
        this._property = value;
    }

    get property() {
        return this._property;
    }

    set message1(value) {
        this._form1.value = {message: value};
    }

    get message1() {
        return this._form1.value.message;
    }

    set message2(value) {
        this._message2 = value;
    }

    get message2() {
        return this._message2;
    }

    set shown(value) {
        super.shown = value;
        this._form1.Children('property').Focus();
    }

    set value(value) {
        this._form1.value = Object.assign(this._form1.value, value);
    }

    get value() {
        return this._form1.value;
    }

    __requestCodeAgainClicked(event, args) {
        this._form1.enabled = false;
        this.RequestCode();
    }

    __changeFormChangeButtonClicked(event, args) {

        if(this._form2.value.code) {

            Auth.Members.ChangeIdentity(this._form2.value.code, this._form1.value.property, this._property).then((session) => {
                this.Dispatch('PropertyChanged', {property: this._property});
            }).catch(response => {
                response.result = JSON.parse(response.result);
                if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                    Object.forEach(response.result.validation, (field, message, index) => {
                        this._validator2.Invalidate(field, message);
                        if(index === 0) {
                            this._form2.FindField(field).Focus();
                        }
                    });
                }
                else {
                    this._validator2.Invalidate('code', response.result.message);
                    this._form2.Children('code').Focus();
                }
            });    

        }

    }

    _stopTimer() {
        Colibri.Common.StopTimer('request-code-timer');
        this._timer.shown = false;
        this._requestCode.shown = true;
    }

    _startTimer() {
        this._timer.shown = true;
        this._requestCode.shown = false;
        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);
        Colibri.Common.StartTimer('request-code-timer', 1000, () => {
            if(this._timeLeft <= 2) {
                this._stopTimer();
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
        this._form1.shown = true;
        

    }

    __sendButtonClicked(event, args) {
        this.RequestCode();
    }

    RequestCode() {
        this._changing = false;
        this._form1.enabled = false;
        this._send.enabled = false;
        Auth.Members.BeginIdentityUpdateProcess(this._form1.value.property, this._property).then((session) => {
            this._form1.shown = false;
            this._send.shown = false;
            this._form2.shown = true;
            this._form2.value = {message: this._message2.replaceAll('%s', '<b>' + this._form1.value.property + '</b>')};
            this._startTimer();
        }).catch(response => {
            response.result = JSON.parse(response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    this._validator.Invalidate(field, message);
                    if(index === 0) {
                        this._form1.FindField(field).Focus();
                    }
                });
            }
            else {
                this._validator.Invalidate('property', response.result.message);
                this._form1.Children('property').Focus();
            }
            this._form1.enabled = true;
            this._send.enabled = true;
            this._form2.shown = false;
            this._stopTimer();
        }); 
    }

}