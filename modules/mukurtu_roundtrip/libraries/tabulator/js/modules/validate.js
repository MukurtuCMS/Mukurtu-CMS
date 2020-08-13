var _typeof = typeof Symbol === "function" && typeof Symbol.iterator === "symbol" ? function (obj) { return typeof obj; } : function (obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; };

/* Tabulator v4.7.2 (c) Oliver Folkerd */

var Validate = function Validate(table) {
	this.table = table;
	this.invalidCells = [];
};

//validate
Validate.prototype.initializeColumn = function (column) {
	var self = this,
	    config = [],
	    validator;

	if (column.definition.validator) {

		if (Array.isArray(column.definition.validator)) {
			column.definition.validator.forEach(function (item) {
				validator = self._extractValidator(item);

				if (validator) {
					config.push(validator);
				}
			});
		} else {
			validator = this._extractValidator(column.definition.validator);

			if (validator) {
				config.push(validator);
			}
		}

		column.modules.validate = config.length ? config : false;
	}
};

Validate.prototype._extractValidator = function (value) {
	var type, params, pos;

	switch (typeof value === "undefined" ? "undefined" : _typeof(value)) {
		case "string":
			pos = value.indexOf(':');

			if (pos > -1) {
				type = value.substring(0, pos);
				params = value.substring(pos + 1);
			} else {
				type = value;
			}

			return this._buildValidator(type, params);
			break;

		case "function":
			return this._buildValidator(value);
			break;

		case "object":
			return this._buildValidator(value.type, value.parameters);
			break;
	}
};

Validate.prototype._buildValidator = function (type, params) {

	var func = typeof type == "function" ? type : this.validators[type];

	if (!func) {
		console.warn("Validator Setup Error - No matching validator found:", type);
		return false;
	} else {
		return {
			type: typeof type == "function" ? "function" : type,
			func: func,
			params: params
		};
	}
};

Validate.prototype.validate = function (validators, cell, value) {
	var self = this,
	    valid = [],
	    invalidIndex = this.invalidCells.indexOf(cell);

	if (validators) {
		validators.forEach(function (item) {
			if (!item.func.call(self, cell.getComponent(), value, item.params)) {
				valid.push({
					type: item.type,
					parameters: item.params
				});
			}
		});
	}

	valid = valid.length ? valid : true;

	if (!cell.modules.validate) {
		cell.modules.validate = {};
	}

	if (valid === true) {
		cell.modules.validate.invalid = false;
		cell.getElement().classList.remove("tabulator-validation-fail");

		if (invalidIndex > -1) {
			this.invalidCells.splice(invalidIndex, 1);
		}
	} else {
		cell.modules.validate.invalid = true;

		if (this.table.options.validationMode !== "manual") {
			cell.getElement().classList.add("tabulator-validation-fail");
		}

		if (invalidIndex == -1) {
			this.invalidCells.push(cell);
		}
	}

	return valid;
};

Validate.prototype.getInvalidCells = function () {
	var output = [];

	this.invalidCells.forEach(function (cell) {
		output.push(cell.getComponent());
	});

	return output;
};

Validate.prototype.clearValidation = function (cell) {
	var invalidIndex;

	if (cell.modules.validate && cell.modules.validate.invalid) {

		cell.element.classList.remove("tabulator-validation-fail");
		cell.modules.validate.invalid = false;

		invalidIndex = this.invalidCells.indexOf(cell);

		if (invalidIndex > -1) {
			this.invalidCells.splice(invalidIndex, 1);
		}
	}
};

Validate.prototype.validators = {

	//is integer
	integer: function integer(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		value = Number(value);
		return typeof value === 'number' && isFinite(value) && Math.floor(value) === value;
	},

	//is float
	float: function float(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		value = Number(value);
		return typeof value === 'number' && isFinite(value) && value % 1 !== 0;
	},

	//must be a number
	numeric: function numeric(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return !isNaN(value);
	},

	//must be a string
	string: function string(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return isNaN(value);
	},

	//maximum value
	max: function max(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return parseFloat(value) <= parameters;
	},

	//minimum value
	min: function min(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return parseFloat(value) >= parameters;
	},

	//starts with  value
	starts: function starts(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return String(value).toLowerCase().startsWith(String(parameters).toLowerCase());
	},

	//ends with  value
	ends: function ends(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return String(value).toLowerCase().endsWith(String(parameters).toLowerCase());
	},

	//minimum string length
	minLength: function minLength(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return String(value).length >= parameters;
	},

	//maximum string length
	maxLength: function maxLength(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		return String(value).length <= parameters;
	},

	//in provided value list
	in: function _in(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		if (typeof parameters == "string") {
			parameters = parameters.split("|");
		}

		return value === "" || parameters.indexOf(value) > -1;
	},

	//must match provided regex
	regex: function regex(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		var reg = new RegExp(parameters);

		return reg.test(value);
	},

	//value must be unique in this column
	unique: function unique(cell, value, parameters) {
		if (value === "" || value === null || typeof value === "undefined") {
			return true;
		}
		var unique = true;

		var cellData = cell.getData();
		var column = cell.getColumn()._getSelf();

		this.table.rowManager.rows.forEach(function (row) {
			var data = row.getData();

			if (data !== cellData) {
				if (value == column.getFieldValue(data)) {
					unique = false;
				}
			}
		});

		return unique;
	},

	//must have a value
	required: function required(cell, value, parameters) {
		return value !== "" && value !== null && typeof value !== "undefined";
	}
};

Tabulator.prototype.registerModule("validate", Validate);