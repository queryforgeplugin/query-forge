// React and ReactDOM are provided by WordPress via wp-element
// They will be available globally as React and ReactDOM
import QueryBuilderModal from './components/QueryBuilderModal';

// Get React and ReactDOM from WordPress globals
const { createRoot } = ReactDOM;

// Wait for Elementor to initialize.
function initQF() {
  if (typeof elementor === 'undefined') {
    setTimeout(initQF, 100);
    return;
  }

  // Store the current widget model globally
  window.qfCurrentWidgetModel = null;
  
  // Hook into the Query Forge widget panel opening.
  elementor.hooks.addAction('panel/open_editor/widget/qf_smart_loop_grid', function(panel, model, view) {
    // Store the model globally for button clicks
    window.qfCurrentWidgetModel = model;
    
    // Attach button listener after panel opens (button is now in DOM)
    setTimeout(function() {
      attachButtonListener();
    }, 100);
  });

  // Also listen for panel:open event as a fallback
  if (elementor.hooks) {
    elementor.hooks.addAction('panel/open_editor', function(panel, model, view) {
      if (model && model.get('widgetType') === 'qf_smart_loop_grid') {
        window.qfCurrentWidgetModel = model;
        setTimeout(function() {
          attachButtonListener();
        }, 100);
      }
    });
  }

  // Initial attachment attempt
  attachButtonListener();
  
  // Use MutationObserver to catch dynamically added buttons
  if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(function(mutations) {
      attachButtonListener();
    });
    
    // Observe the Elementor panel for changes
    const panelElement = document.querySelector('.elementor-panel');
    if (panelElement) {
      observer.observe(panelElement, {
        childList: true,
        subtree: true
      });
    }
  }
}

// Function to attach button click listener
function attachButtonListener() {
  const buttonSelector = '.elementor-control-qf_open_builder button, .elementor-control-qf_open_builder .elementor-button, .elementor-control-qf_open_builder';
  
  // Remove existing listeners to prevent duplicates
  jQuery(document).off('click', buttonSelector);
  
  // Attach click listener
  jQuery(document).on('click', buttonSelector, function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Get model from the stored global model
    let widgetModel = window.qfCurrentWidgetModel;
    
    // If not found, try multiple fallback methods
    if (!widgetModel) {
      // Try to get from panel current view
      if (elementor.panels && elementor.panels.currentView) {
        const currentView = elementor.panels.currentView;
        if (currentView.model && currentView.model.get('widgetType') === 'qf_smart_loop_grid') {
          widgetModel = currentView.model;
          window.qfCurrentWidgetModel = widgetModel;
        }
      }
      
      // Try to get from Elementor's current element
      if (!widgetModel && elementor.getCurrentElement) {
        const currentElement = elementor.getCurrentElement();
        if (currentElement && currentElement.model && currentElement.model.get('widgetType') === 'qf_smart_loop_grid') {
          widgetModel = currentElement.model;
          window.qfCurrentWidgetModel = widgetModel;
        }
      }
      
      // Try to get from the control's parent
      if (!widgetModel) {
        const $control = jQuery(this).closest('.elementor-control');
        if ($control.length) {
          const controlName = $control.data('setting');
          // Try to find the widget model from the panel
          if (elementor.panels && elementor.panels.currentView && elementor.panels.currentView.model) {
            widgetModel = elementor.panels.currentView.model;
            if (widgetModel && widgetModel.get('widgetType') === 'qf_smart_loop_grid') {
              window.qfCurrentWidgetModel = widgetModel;
            } else {
              widgetModel = null;
            }
          }
        }
      }
    }
    
    if (widgetModel && widgetModel.get('widgetType') === 'qf_smart_loop_grid') {
      mountReactModal(widgetModel);
    } else {
      console.warn('Query Forge: Could not find widget model. Current widget:', widgetModel ? widgetModel.get('widgetType') : 'none');
      alert('Error: Could not find Query Forge widget. Please click on the Query Forge widget in the editor, then try again.');
      }
    });
}

// Initialize when DOM is ready and Elementor is available.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initQF);
} else {
  initQF();
}

// Also try when Elementor fires its init event.
window.addEventListener('elementor:init', initQF);

function mountReactModal(widgetModel) {
  if (!widgetModel) {
    alert('Error: Could not find widget. Please try selecting the widget again.');
    return;
  }

  // Check if modal is already open - if so, don't open another.
  const existingRoot = document.getElementById('qf-root');
  if (existingRoot) {
    try {
      // Try to unmount existing React root if it exists.
      if (window.qfReactRoot) {
        window.qfReactRoot.unmount();
      }
      existingRoot.remove();
    } catch(e) {
      // Silently handle cleanup errors
    }
  }

  // Create a DOM node for our React Modal.
  const rootNode = document.createElement('div');
  rootNode.id = 'qf-root';
  document.body.appendChild(rootNode);

  try {
    const root = createRoot(rootNode);
    // Store root globally so we can clean it up if needed.
    window.qfReactRoot = root;
    
    // Get existing data from Elementor model.
    const graphState = widgetModel.getSetting('qf_graph_state');
    const logicJson = widgetModel.getSetting('qf_logic_json');

    // Use graph state if available, otherwise try logic json.
    const initialData = graphState || logicJson;

    // Mount the App.
    root.render(
      React.createElement(QueryBuilderModal, {
        initialData: initialData,
        onSave: (data) => {
          try {
            if (!widgetModel) {
              alert('Error: Widget model not found. Please try again.');
              return;
            }
            
            // Use Elementor's command system to update settings - this properly triggers change detection.
            // This ensures the "Update" button activates and changes are saved to the database.
            let settingsUpdated = false;
            
            if (window.$e && $e.run && elementor && elementor.getContainer) {
              try {
                // Get the container for this widget model.
                const container = elementor.getContainer(widgetModel.get('id'));
                
                if (container) {
                  // Use Elementor's command system to update widget settings.
                  // This method properly triggers change detection and activates the "Update" button.
                  $e.run('document/elements/settings', {
                    container: container,
                    settings: {
                      qf_graph_state: data.graphState,
                      qf_logic_json: data.logicJson,
                    },
                    options: {
                      external: true, // Mark as external change to trigger save detection
                    }
                  });
                  settingsUpdated = true;
                }
              } catch(e) {
                // Fallback to setSetting if command system fails
              }
            }
            
            // Fallback to direct setSetting if command system fails or isn't available.
            if (!settingsUpdated) {
              widgetModel.setSetting('qf_graph_state', data.graphState);
              widgetModel.setSetting('qf_logic_json', data.logicJson);
              
              // Try to manually trigger change detection.
              if (widgetModel.trigger) {
                widgetModel.trigger('change');
                widgetModel.trigger('change:settings');
              }
              
              // Also try to trigger document change via Elementor's saver.
              if (elementor && elementor.saver && elementor.saver.setEditorChange) {
                elementor.saver.setEditorChange(true);
              }
            }
            
            // Verify settings were set.
            const savedLogicJson = widgetModel.getSetting('qf_logic_json');
            
            if (!savedLogicJson) {
              alert('Warning: Settings may not have been saved. Please check and try again.');
              return; // Don't continue if save failed.
            }
            
            // Try to trigger a preview refresh, but don't break if it fails.
            setTimeout(() => {
              try {
                if (elementor && typeof elementor.reloadPreview === 'function') {
                  if (widgetModel && widgetModel.getSetting) {
                    const testSetting = widgetModel.getSetting('qf_logic_json');
                    if (testSetting) {
                      setTimeout(() => {
                        try {
                          elementor.reloadPreview();
                        } catch(e) {
                          // Silently handle preview reload errors
                        }
                      }, 500);
                    }
                  }
                }
              } catch(e) {
                // Silently handle preview refresh errors
              }
            }, 200);
            
            // Close modal after successful save.
            setTimeout(() => {
              try {
                root.unmount();
                if (rootNode && rootNode.parentNode) {
                  rootNode.remove();
                }
              } catch(e) {
                // Silently handle cleanup errors
              }
            }, 100);
            
          } catch(error) {
            alert('Error saving query. Please try again. Error: ' + (error.message || error));
            // Don't close modal on error so user can try again.
          }
        },
        onClose: () => {
          try {
            root.unmount();
            window.qfReactRoot = null;
            if (rootNode && rootNode.parentNode) {
              rootNode.remove();
            }
          } catch(e) {
            // Silently handle cleanup errors
          }
        }
      })
    );
  } catch (error) {
    alert('Error opening Query Builder: ' + (error.message || error) + '. Please try refreshing the editor.');
    if (rootNode && rootNode.parentNode) {
      rootNode.remove();
    }
  }
}
