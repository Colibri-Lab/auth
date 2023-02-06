App.Modules.Auth.Components.ChangeIdentityForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ChangeIdentityForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-confirmation-form-component'); 
        this._property = 'none';
        this._changing = false;

        this._form1 = this.Children('form-container/form1');
        this._validator1 = new Colibri.UI.SimpleFormValidator(this._form1);

        this._form2 = this.Children('form-container/form2');
        this._validator2 = new Colibri.UI.SimpleFormValidator(this._form2);

        this._send = this.Children('timer-container/send');

        this._timer = this.Children('timer-container/timer');
        this._timerTemplate = this._timer.value;
        this._requestCode = this.Children('timer-container/request-code-again');

        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);

        this._form1.AddHandler('Changed', (event, args) => {
            this._send.enabled = this._validator1.Status();
        });
        this._form2.AddHandler('Changed', (event, args) => {
            if(this._validator2.ValidateAll()) {
                if(this._changing) {
                    return;
                }
                this._changing = true;
                this.__changeFormChangeButtonClicked(event, args);
            }
        });

        this._send.AddHandler('Clicked', (event, args) => this.__sendButtonClicked(event, args));
        this._requestCode.AddHandler('Clicked', (event, args) => this.__requestCodeAgainClicked(event, args));

    } 

    _registerEvents() {
        this.RegisterEvent('PropertyChanged', true, 'Сойвство изменено');
    }

    /**
     * Sets the identity property
     * @type {string}
     */
    set property(value) {
        this._property = value;
    }

    get property() {
        return this._property;
    }

    /**
     * Sets the form 1 message
     * @type {string}
     */
    set message1(value) {
        this._form1.value = {message: value};
    }

    get message1() {
        return this._form1.value.message;
    }

    /**
     * Sets the form 2 message
     * @type {string}
     */
    set message2(value) {
        this._message2 = value;
    }

    get message2() {
        return this._message2;
    }

    get desc() {
        return this._form1.Children('property').title;
    }
    /**
     * Sets description
     * @type {string}
     */
    set desc(value) {
        this._form1.Children('property').title = value;
    }

    /**
     * Shows the component
     * @type {boolean}
     */
    set shown(value) {
        super.shown = value;
        this._form1.Children('property').Focus();
    }

    /**
     * Value object
     * @type {Object}
     */
    set value(value) {
        this._form1.value = Object.assign(this._form1.value, value);
    }

    get value() {
        return this._form1.value;
    }

    __requestCodeAgainClicked(event, args) {
        
        if(!this._validator1.ValidateAll()) {
            return;
        }
        
        this._form1.enabled = false;
        this.RequestCode();
    }

    __changeFormChangeButtonClicked(event, args) {

        if(this._form2.value.code) {

            Auth.Members.ChangeIdentity(this._form2.value.code, this._form1.value.property, this._property).then((session) => {
                this.Dispatch('PropertyChanged', {property: this._property});
                this._changing = false;
            }).catch(response => {
                response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
                if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                    Object.forEach(response.result.validation, (field, message, index) => {
                        this._validator2.Invalidate(field, message);
                        if(index === 0) {
                            this._form2.FindField(field).Focus();
                        }
                    });
                }
                else {
                    this._validator2.Invalidate('form', response.result.message);
                    this._form2.Focus();
                }
                this._changing = false;
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
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    this._validator1.Invalidate(field, message);
                    if(index === 0) {
                        this._form1.FindField(field).Focus();
                    }
                });
            }
            else {
                this._validator1.Invalidate('property', response.result.message);
                this._form1.Children('property').Focus();
            }
            this._form1.enabled = true;
            this._send.enabled = true;
            this._form2.shown = false;
            this._stopTimer();
        }); 
    }

}