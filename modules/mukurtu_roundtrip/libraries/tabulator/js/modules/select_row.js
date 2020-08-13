var _typeof = typeof Symbol === "function" && typeof Symbol.iterator === "symbol" ? function (obj) { return typeof obj; } : function (obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; };

/* Tabulator v4.7.2 (c) Oliver Folkerd */

var SelectRow = function SelectRow(table) {
	this.table = table; //hold Tabulator object
	this.selecting = false; //flag selecting in progress
	this.lastClickedRow = false; //last clicked row
	this.selectPrev = []; //hold previously selected element for drag drop selection
	this.selectedRows = []; //hold selected rows
	this.headerCheckboxElement = null; // hold header select element
};

SelectRow.prototype.clearSelectionData = function (silent) {
	this.selecting = false;
	this.lastClickedRow = false;
	this.selectPrev = [];
	this.selectedRows = [];

	if (!silent) {
		this._rowSelectionChanged();
	}
};

SelectRow.prototype.initializeRow = function (row) {
	var self = this,
	    element = row.getElement();

	// trigger end of row selection
	var endSelect = function endSelect() {

		setTimeout(function () {
			self.selecting = false;
		}, 50);

		document.body.removeEventListener("mouseup", endSelect);
	};

	row.modules.select = { selected: false };

	//set row selection class
	if (self.table.options.selectableCheck.call(this.table, row.getComponent())) {
		element.classList.add("tabulator-selectable");
		element.classList.remove("tabulator-unselectable");

		if (self.table.options.selectable && self.table.options.selectable != "highlight") {
			if (self.table.options.selectableRangeMode === "click") {
				element.addEventListener("click", function (e) {
					if (e.shiftKey) {
						self.table._clearSelection();
						self.lastClickedRow = self.lastClickedRow || row;

						var lastClickedRowIdx = self.table.rowManager.getDisplayRowIndex(self.lastClickedRow);
						var rowIdx = self.table.rowManager.getDisplayRowIndex(row);

						var fromRowIdx = lastClickedRowIdx <= rowIdx ? lastClickedRowIdx : rowIdx;
						var toRowIdx = lastClickedRowIdx >= rowIdx ? lastClickedRowIdx : rowIdx;

						var rows = self.table.rowManager.getDisplayRows().slice(0);
						var toggledRows = rows.splice(fromRowIdx, toRowIdx - fromRowIdx + 1);

						if (e.ctrlKey || e.metaKey) {
							toggledRows.forEach(function (toggledRow) {
								if (toggledRow !== self.lastClickedRow) {

									if (self.table.options.selectable !== true && !self.isRowSelected(row)) {
										if (self.selectedRows.length < self.table.options.selectable) {
											self.toggleRow(toggledRow);
										}
									} else {
										self.toggleRow(toggledRow);
									}
								}
							});
							self.lastClickedRow = row;
						} else {
							self.deselectRows(undefined, true);

							if (self.table.options.selectable !== true) {
								if (toggledRows.length > self.table.options.selectable) {
									toggledRows = toggledRows.slice(0, self.table.options.selectable);
								}
							}

							self.selectRows(toggledRows);
						}
						self.table._clearSelection();
					} else if (e.ctrlKey || e.metaKey) {
						self.toggleRow(row);
						self.lastClickedRow = row;
					} else {
						self.deselectRows(undefined, true);
						self.selectRows(row);
						self.lastClickedRow = row;
					}
				});
			} else {
				element.addEventListener("click", function (e) {
					if (!self.table.modExists("edit") || !self.table.modules.edit.getCurrentCell()) {
						self.table._clearSelection();
					}

					if (!self.selecting) {
						self.toggleRow(row);
					}
				});

				element.addEventListener("mousedown", function (e) {
					if (e.shiftKey) {
						self.table._clearSelection();

						self.selecting = true;

						self.selectPrev = [];

						document.body.addEventListener("mouseup", endSelect);
						document.body.addEventListener("keyup", endSelect);

						self.toggleRow(row);

						return false;
					}
				});

				element.addEventListener("mouseenter", function (e) {
					if (self.selecting) {
						self.table._clearSelection();
						self.toggleRow(row);

						if (self.selectPrev[1] == row) {
							self.toggleRow(self.selectPrev[0]);
						}
					}
				});

				element.addEventListener("mouseout", function (e) {
					if (self.selecting) {
						self.table._clearSelection();
						self.selectPrev.unshift(row);
					}
				});
			}
		}
	} else {
		element.classList.add("tabulator-unselectable");
		element.classList.remove("tabulator-selectable");
	}
};

//toggle row selection
SelectRow.prototype.toggleRow = function (row) {
	if (this.table.options.selectableCheck.call(this.table, row.getComponent())) {
		if (row.modules.select && row.modules.select.selected) {
			this._deselectRow(row);
		} else {
			this._selectRow(row);
		}
	}
};

//select a number of rows
SelectRow.prototype.selectRows = function (rows) {
	var _this = this;

	var rowMatch;

	switch (typeof rows === "undefined" ? "undefined" : _typeof(rows)) {
		case "undefined":
			this.table.rowManager.rows.forEach(function (row) {
				_this._selectRow(row, true, true);
			});

			this._rowSelectionChanged();
			break;

		case "string":

			rowMatch = this.table.rowManager.findRow(rows);

			if (rowMatch) {
				this._selectRow(rowMatch, true, true);
			} else {
				this.table.rowManager.getRows(rows).forEach(function (row) {
					_this._selectRow(row, true, true);
				});
			}

			this._rowSelectionChanged();
			break;

		default:
			if (Array.isArray(rows)) {
				rows.forEach(function (row) {
					_this._selectRow(row, true, true);
				});

				this._rowSelectionChanged();
			} else {
				this._selectRow(rows, false, true);
			}
			break;
	}
};

//select an individual row
SelectRow.prototype._selectRow = function (rowInfo, silent, force) {
	var index;

	//handle max row count
	if (!isNaN(this.table.options.selectable) && this.table.options.selectable !== true && !force) {
		if (this.selectedRows.length >= this.table.options.selectable) {
			if (this.table.options.selectableRollingSelection) {
				this._deselectRow(this.selectedRows[0]);
			} else {
				return false;
			}
		}
	}

	var row = this.table.rowManager.findRow(rowInfo);

	if (row) {
		if (this.selectedRows.indexOf(row) == -1) {
			if (!row.modules.select) {
				row.modules.select = {};
			}

			row.modules.select.selected = true;
			if (row.modules.select.checkboxEl) {
				row.modules.select.checkboxEl.checked = true;
			}
			row.getElement().classList.add("tabulator-selected");

			this.selectedRows.push(row);

			if (this.table.options.dataTreeSelectPropagate) {
				this.childRowSelection(row, true);
			}

			if (!silent) {
				this.table.options.rowSelected.call(this.table, row.getComponent());
			}

			this._rowSelectionChanged(silent);
		}
	} else {
		if (!silent) {
			console.warn("Selection Error - No such row found, ignoring selection:" + rowInfo);
		}
	}
};

SelectRow.prototype.isRowSelected = function (row) {
	return this.selectedRows.indexOf(row) !== -1;
};

//deselect a number of rows
SelectRow.prototype.deselectRows = function (rows, silent) {
	var self = this,
	    rowCount;

	if (typeof rows == "undefined") {

		rowCount = self.selectedRows.length;

		for (var i = 0; i < rowCount; i++) {
			self._deselectRow(self.selectedRows[0], true);
		}

		self._rowSelectionChanged(silent);
	} else {
		if (Array.isArray(rows)) {
			rows.forEach(function (row) {
				self._deselectRow(row, true);
			});

			self._rowSelectionChanged(silent);
		} else {
			self._deselectRow(rows, silent);
		}
	}
};

//deselect an individual row
SelectRow.prototype._deselectRow = function (rowInfo, silent) {
	var self = this,
	    row = self.table.rowManager.findRow(rowInfo),
	    index;

	if (row) {
		index = self.selectedRows.findIndex(function (selectedRow) {
			return selectedRow == row;
		});

		if (index > -1) {

			if (!row.modules.select) {
				row.modules.select = {};
			}

			row.modules.select.selected = false;
			if (row.modules.select.checkboxEl) {
				row.modules.select.checkboxEl.checked = false;
			}
			row.getElement().classList.remove("tabulator-selected");
			self.selectedRows.splice(index, 1);

			if (this.table.options.dataTreeSelectPropagate) {
				this.childRowSelection(row, false);
			}

			if (!silent) {
				self.table.options.rowDeselected.call(this.table, row.getComponent());
			}

			self._rowSelectionChanged(silent);
		}
	} else {
		if (!silent) {
			console.warn("Deselection Error - No such row found, ignoring selection:" + rowInfo);
		}
	}
};

SelectRow.prototype.getSelectedData = function () {
	var data = [];

	this.selectedRows.forEach(function (row) {
		data.push(row.getData());
	});

	return data;
};

SelectRow.prototype.getSelectedRows = function () {

	var rows = [];

	this.selectedRows.forEach(function (row) {
		rows.push(row.getComponent());
	});

	return rows;
};

SelectRow.prototype._rowSelectionChanged = function (silent) {
	if (this.headerCheckboxElement) {
		if (this.selectedRows.length === 0) {
			this.headerCheckboxElement.checked = false;
			this.headerCheckboxElement.indeterminate = false;
		} else if (this.table.rowManager.rows.length === this.selectedRows.length) {
			this.headerCheckboxElement.checked = true;
			this.headerCheckboxElement.indeterminate = false;
		} else {
			this.headerCheckboxElement.indeterminate = true;
			this.headerCheckboxElement.checked = false;
		}
	}

	if (!silent) {
		this.table.options.rowSelectionChanged.call(this.table, this.getSelectedData(), this.getSelectedRows());
	}
};

SelectRow.prototype.registerRowSelectCheckbox = function (row, element) {
	if (!row._row.modules.select) {
		row._row.modules.select = {};
	}

	row._row.modules.select.checkboxEl = element;
};

SelectRow.prototype.registerHeaderSelectCheckbox = function (element) {
	this.headerCheckboxElement = element;
};

SelectRow.prototype.childRowSelection = function (row, select) {
	var children = this.table.modules.dataTree.getChildren(row);

	if (select) {
		for (var _iterator = children, _isArray = Array.isArray(_iterator), _i = 0, _iterator = _isArray ? _iterator : _iterator[Symbol.iterator]();;) {
			var _ref;

			if (_isArray) {
				if (_i >= _iterator.length) break;
				_ref = _iterator[_i++];
			} else {
				_i = _iterator.next();
				if (_i.done) break;
				_ref = _i.value;
			}

			var child = _ref;

			this._selectRow(child, true);
		}
	} else {
		for (var _iterator2 = children, _isArray2 = Array.isArray(_iterator2), _i2 = 0, _iterator2 = _isArray2 ? _iterator2 : _iterator2[Symbol.iterator]();;) {
			var _ref2;

			if (_isArray2) {
				if (_i2 >= _iterator2.length) break;
				_ref2 = _iterator2[_i2++];
			} else {
				_i2 = _iterator2.next();
				if (_i2.done) break;
				_ref2 = _i2.value;
			}

			var _child = _ref2;

			this._deselectRow(_child, true);
		}
	}
};

Tabulator.prototype.registerModule("selectRow", SelectRow);