TYPO3.Ice.View.InsertElementsPanelClass.Element = TYPO3.Ice.View.InsertElementsPanelClass.Element.extend({
	enabled: (function() {
		if (this.getPath('projectElementType.options._isTopLevel') ||
			this.getPath('projectElementType.enableTypes').indexOf('all') > -1) {
			return true;
		}
		var currentlySelectedRenderable = this.get('currentlySelectedElement');
		if(currentlySelectedRenderable && this.getPath('projectElementType.enableTypes').indexOf(currentlySelectedRenderable.get('type').split(':')[1])>-1) {
			return true;
		}
		return false;

	}).property('projectElementType', 'currentlySelectedElement').cacheable(),
	click:function () {
		var currentlySelectedRenderable, defaultValues, identifier, indexInParent, newRenderable, parentRenderablesArray, referenceRenderable,
				_this = this;
		currentlySelectedRenderable = this.get('currentlySelectedElement');
		if (!currentlySelectedRenderable) {
			return
		}
		if (!this.get('enabled')) {
			return;
		}
		defaultValues = this.getPath('projectElementType.options.predefinedDefaults') || {};
		identifier = this.getNextFreeIdentifier();
		newRenderable = TYPO3.Ice.Model.Renderable.create($.extend({
		   type:this.getPath('projectElementType.key'),
		   identifier:identifier,
		   label:identifier
		}, defaultValues));
		if (this.getPath('projectElementType.group') == 'packageElements') {
			topLevelContainer = this.addTopLevelContainer(this.getPath('projectElementType.label'));
			topLevelContainer.get('renderables').pushObject(newRenderable);
		} else {
			if(currentlySelectedRenderable.getPath('typeDefinition.options._isCompositeRenderable')) {
				currentlySelectedRenderable.get('renderables').pushObject(newRenderable);
			} else {
				referenceRenderable = currentlySelectedRenderable;
				if (referenceRenderable.findEnclosingCompositeRenderableWhichIsNotOnTopLevel()) {
					referenceRenderable = referenceRenderable.findEnclosingCompositeRenderableWhichIsNotOnTopLevel();
					referenceRenderable.get('renderables').pushObject(newRenderable);
				}
			}
		}
		return window.setTimeout(function () {
			return _this.set('currentlySelectedElement', newRenderable);
		}, 10);
	},
	addTopLevelContainer:function (containerIdentifier) {
		var topLevelContainers = TYPO3.Ice.Model.Project.get('projectDefinition').get('renderables');
		for (var i = 0; i < topLevelContainers.length; i++) {
		   if (topLevelContainers[i].get('identifier') == containerIdentifier) {
			   return topLevelContainers[i];
		   }
		}
		newContainer = TYPO3.Ice.Model.Renderable.create($.extend({
			 type:'TYPO3.PackageBuilder:Container',
			 identifier:containerIdentifier,
			 label:containerIdentifier + 's'
		 }, {}));
		TYPO3.Ice.Model.Project.get('projectDefinition').get('renderables').pushObject(newContainer);
		return newContainer;
	}
});




window.setTimeout(
  function() {
	  console.log('Setting empty default package');
	  if(!TYPO3.Ice.Model.Project.get('projectDefinition')) {
		TYPO3.Ice.Model.Project.set('projectDefinition', TYPO3.Ice.Model.Renderable.create({identifier:'package1',label:'My Package',type:'TYPO3.PackageBuilder:Package'}));
	  }
  },
  200
);