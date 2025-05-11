App.Modules.Auth.Components.ConfirmationForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ConfirmationForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-confirmation-form-component'); 
        this._property = 'none';

        this._form1 = this.Children('form-container/form1');
        this._form2 = this.Children('form-container/form2');
        this._validator1 = new Colibri.UI.SimpleFormValidator(this._form1);
        this._validator2 = new Colibri.UI.SimpleFormValidator(this._form2);
        this._send = this.Children('button-container/send');

        this._timerContainer = this.Children('timer-container');
        this._timer = this.Children('timer-container/timer');
        this._timerTemplate = this._timer.value;
        this._requestCode = this.Children('timer-container/request-code-again');

        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);

        this._send.AddHandler('Clicked', (event, args) => this.__sendClicked(event, args));

        this._form2.AddHandler('Changed', (event, args) => {
            if(this._validator2.ValidateAll()) {
                if(this._confirming) {
                    return;
                }
                this._confirming = true;
                this.__confirmationFormConfirmationButtonClicked(event, args);
                
            }
        });

        this._requestCode.AddHandler('Clicked', (event, args) => this.__requestCodeAgainClicked(event, args));
        this._form1.AddHandler('Changed', (event, args) => {
            this.Dispatch('ExternalValidation', Object.assign(args, {validator: this._validator1, property: this._property, form: this._form1, submit: this._send}));
        });

    } 

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __sendClicked(event, args) {
        this.RequestCode();
    }

    /** @protected */
    _registerEvents() {
        super._registerEvents();
        this.RegisterEvent('PropertyConfirmed', true, 'When property is confirmed');
        this.RegisterEvent('ExternalValidation', true, 'When external validation is needed');
    }

    /**
     * Do nothing
     * @type {Value object}
     */
    get value() {
    }
    /**
     * Do nothing
     * @type {Value object}
     */
    set value(value) {
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
        this._form1.Children('property').Focus();

        this._setFields();

    }

    _setFields() {
        if(this._property === 'email') {
            const fields = this._form1.fields;
            fields.message.desc = this._message1;
            fields.property.desc = '#{auth-confirmationform-property-email-desc}';
            fields.property.params.validate = [
                {
                    message: '#{auth-confirmationform-property-email-validation1}',
                    method: '(field, validator) => !!field.value && field.value.isEmail()'
                }
            ];
            this._form1.fields = fields;

        } else if(this._property === 'phone') {
            const fields = this._form1.fields;
            fields.message.desc = this._message1;
            fields.property.desc = '#{auth-confirmationform-property-phone-desc}';
            fields.property.params.validate = [
                {
                    message: '#{auth-confirmationform-property-phone-validation1}',
                    method: '(field, validator) => !!field.value'
                }
            ];
            // fields.property.params.mask = '+44 (9) 999 999 999',
            this._form1.fields = fields;
        }
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __requestCodeAgainClicked(event, args) {
        this._form2.enabled = false;
        this.RequestCode();
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __confirmationFormConfirmationButtonClicked(event, args) {

        if(this._form2.value.code) {

            Auth.Members.ConfirmProperty(this._form2.value.code, this._property, this._form1.value.property).then((session) => {
                this.Dispatch('PropertyConfirmed', {property: this._property, value: this._form1.value.property});
                this._confirming = false;
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
                this._confirming = false;
            });    

        }

    }

    _startTimer() {
        this._send.shown = false;
        this._timerContainer.shown = true;
        this._timer.shown = true;
        this._requestCode.shown = false;
        this._timeLeft = 60;
        this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);
        Colibri.Common.StartTimer('request-code-timer', 1000, () => {
            if(this._timeLeft <= 2) {
                Colibri.Common.StopTimer('request-code-timer');
                this._timerContainer.shown = true;
                this._timer.shown = false;
                this._requestCode.shown = true;
                return;
            }
            this._timeLeft --;
            this._timer.shown = true;
            this._timerContainer.shown = true;
            this._requestCode.shown = false;
            this._timer.value = this._timerTemplate.replaceAll('%s', this._timeLeft);
        });
    }

    Show(property) {
        if(property) {
            this._property = property;
        }
        super.Show();

    }

    RequestCode() {
        if(!this._validator1.ValidateAll()) {
            return;
        }

        App.Loading.Show();
        this._form1.shown = false;
        this._form2.shown = true;
        this._form2.enabled = false;
        this._send.shown = false;
        Auth.Members.BeginConfirmationProcess(this._property, this._form1.value.property).then((session) => {
            App.Loading.Hide();
            this._form2.enabled = true;
            this._confirming = false;
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
                this._validator1.Invalidate('form', response.result.message);
                this._form1.Focus();
            }
            this._confirming = false;
        }); 
    }

    /**
     * Message text
     * @type {string}
     */
    get message1() {
        return this._form1.value.message;
    }
    /**
     * Message text
     * @type {string}
     */
    set message1(value) {
        this._message1 = value;
        this.value = {
            message: this._message1
        };
    }

    /**
     * Message text
     * @type {string}
     */
    get message2() {
        return this._form2.value.message;
    }
    /**
     * Message text
     * @type {string}
     */
    set message2(value) {
        this._message2 = value;
        this.value = {
            message: this._message2
        };
    }

    /**
     * Message text
     * @type {String}
     */
    get message3() {
        return this._message3;
    }
    /**
     * Message text
     * @type {String}
     */
    set message3(value) {
        this._message3 = value;
        this._form2.FindField('code').title = this._message3;   
    }

    /**
     * Confirmation property
     * @type {phone,email}
     */
    get mode() {
        return this._mode;
    }
    /**
     * Confirmation property
     * @type {phone,email}
     */
    set mode(value) {
        this._mode = value;
        if(value === 'phone') {
            const fields = this._form1.fields;
            fields.property.params.pattern = '[0-9]*';
            fields.property.params.inputmode = 'numeric';
            this._form1.fields = fields;
        } else {
            const fields = this._form1.fields;
            delete fields.property.params.pattern;
            delete fields.property.params.inputmode;
            this._form1.fields = fields;
        }
    }

}