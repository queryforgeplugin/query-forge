// React is provided by WordPress via wp-element (available globally)
const { useState, useEffect, useRef } = React;
import UpsellModal from './UpsellModal';

const NodeSettingsPanel = ({ node, onUpdate, onClose, sourceNodes = [] }) => {
  // Get PRO status from global config
  const isPro = window.QueryForgeConfig?.isPro || false;
  const [upsellModal, setUpsellModal] = useState({ isOpen: false, featureName: '', description: '' });
  const [settings, setSettings] = useState(node?.data || {});
  const [fields, setFields] = useState([]); // All fields: standard + meta
  const [loadingMetaKeys, setLoadingMetaKeys] = useState(false);
  const [showCustomField, setShowCustomField] = useState(false);
  const [showDynamicTags, setShowDynamicTags] = useState(false);
  const [activeField, setActiveField] = useState(null); // Track which field is active for dynamic tags
  const dynamicTagsRef = useRef(null);

  useEffect(() => {
    setSettings(node?.data || {});
    // Check if field is custom (not in dropdown).
    if (node?.type === 'filter' && node?.data?.field) {
      setShowCustomField(node?.data?.isCustomField || false);
    }
    setShowDynamicTags(false); // Close dynamic tag menu when node changes
    setActiveField(null); // Reset active field when node changes
  }, [node]);

  // Fetch meta keys when filter node is selected and we have post types.
  useEffect(() => {
    if (node?.type === 'filter' && sourceNodes.length > 0) {
      // Get post types from source nodes - use first one for now.
      // In future, could merge meta keys from all source post types.
      const firstSource = sourceNodes[0];
      const postType = firstSource?.data?.postType || 'post';
      fetchMetaKeys(postType);
    }
  }, [node?.type, sourceNodes]);

  // Close dynamic tags dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dynamicTagsRef.current && !dynamicTagsRef.current.contains(event.target)) {
        setShowDynamicTags(false);
      }
    };

    if (showDynamicTags) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [showDynamicTags]);

  // Insert dynamic tag into value field
  const insertDynamicTag = (tag) => {
    const currentValue = settings.value || '';
    const newValue = currentValue + (currentValue ? ' ' : '') + tag;
    const updatedSettings = { ...settings, value: newValue };
    setSettings(updatedSettings);
    onUpdate(node.id, updatedSettings);
    setShowDynamicTags(false);
  };

  if (!node) {
    return null;
  }

  const handleSave = (e) => {
    if (e) {
      e.stopPropagation();
      e.preventDefault();
    }
    onUpdate(node.id, settings);
    // Don't close the panel - keep it open so user can continue editing
    // onClose();
  };

  const fetchMetaKeys = async (postType) => {
    setLoadingMetaKeys(true);
    const ajaxUrl = window.QueryForgeConfig?.ajaxUrl;
    const nonce = window.QueryForgeConfig?.nonce || '';
    if (!ajaxUrl) {
      setLoadingMetaKeys(false);
      return;
    }
    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'query_forge_get_meta_keys',
          post_type: postType,
          nonce,
        }),
      });
      const data = await response.json();
      if (data.success) {
        // Use new 'fields' array if available, fallback to 'meta_keys' for backward compatibility.
        if (data.data.fields && Array.isArray(data.data.fields)) {
          setFields(data.data.fields);
        } else if (data.data.meta_keys) {
          // Legacy format - convert to new format.
          const legacyFields = data.data.meta_keys.map(key => ({
            key: key,
            label: key,
            type: 'meta'
          }));
          setFields(legacyFields);
        }
      }
    } catch (error) {
      // Silently handle fetch errors
    } finally {
      setLoadingMetaKeys(false);
    }
  };

  const handleFieldChange = (value) => {
    if (value === '__custom__') {
      setShowCustomField(true);
      setSettings({ ...settings, field: '', isCustomField: true });
    } else {
      setShowCustomField(false);
      setSettings({ ...settings, field: value, isCustomField: false });
      onUpdate(node.id, { ...settings, field: value, isCustomField: false });
    }
  };

  const handleCustomFieldChange = (value) => {
    setSettings({ ...settings, field: value, isCustomField: true });
  };

  const renderSourceSettings = () => {
    const qfConfig = window.QueryForgeConfig || {};
    const postTypes = qfConfig.postTypes || [];
    
    // Determine source type from current postType value or legacy postTypeCategory
    // If postType is 'post', sourceType is 'posts'
    // If postType is 'page', sourceType is 'pages'
    // Otherwise, assume it's a CPT (sourceType is 'cpts')
    const getSourceType = () => {
      // Check for legacy postTypeCategory first (backward compatibility)
      if (settings.postTypeCategory) {
        return settings.postTypeCategory;
      }
      
      // Otherwise infer from postType
      const currentPostType = settings.postType || 'post';
      if (currentPostType === 'post') {
        return 'posts';
      } else if (currentPostType === 'page') {
        return 'pages';
      } else {
        return 'cpts';
      }
    };
    
    const sourceType = settings.sourceType || getSourceType();
    
    // Built-in WordPress post types to exclude from CPTs list
    const builtInPostTypes = [
      'post', 'page', 'attachment', 'revision', 'nav_menu_item', 
      'custom_css', 'customize_changeset', 'oembed', 'user_request',
      'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
      'wp_navigation', 'wp_font_family', 'wp_font_face'
    ];
    
    // Filter post types based on source type selection
    const getFilteredPostTypes = () => {
      if (sourceType === 'posts') {
        return [{ name: 'post', label: 'Posts' }];
      } else if (sourceType === 'pages') {
        return [{ name: 'page', label: 'Pages' }];
      } else if (sourceType === 'cpts') {
        // Return only custom post types (exclude built-in types)
        return postTypes.filter(pt => !builtInPostTypes.includes(pt.name));
      }
      return [];
    };
    
    const filteredPostTypes = getFilteredPostTypes();
    
    return (
    <div>
      {/* First dropdown: Source Type */}
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Source Type:
        <select
          value={sourceType}
          onChange={(e) => {
            const newSourceType = e.target.value;
            let defaultPostType = 'post';
            
            // Set default post type based on source type
            if (newSourceType === 'posts') {
              defaultPostType = 'post';
            } else if (newSourceType === 'pages') {
              defaultPostType = 'page';
            } else if (newSourceType === 'cpts') {
              // Use first CPT as default if available
              const cpts = postTypes.filter(pt => !builtInPostTypes.includes(pt.name));
              if (cpts.length > 0) {
                defaultPostType = cpts[0].name;
              }
            }
            
            const updatedSettings = { 
              ...settings, 
              sourceType: newSourceType,
              postType: defaultPostType
            };
            setSettings(updatedSettings);
            onUpdate(node.id, updatedSettings);
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
        >
          <option value="posts">Posts</option>
          <option value="pages">Pages</option>
          <option value="cpts">Custom Post Types</option>
        </select>
      </label>
      
      {/* Second dropdown: Post Type (filtered by Source Type) */}
      <label style={{ display: 'block', marginBottom: '10px', marginTop: '10px' }}>
        Post Type:
        <select
          value={settings.postType || (sourceType === 'posts' ? 'post' : sourceType === 'pages' ? 'page' : '')}
          onChange={(e) => {
            const newPostType = e.target.value;
            const updatedSettings = { ...settings, postType: newPostType };
            setSettings(updatedSettings);
            onUpdate(node.id, updatedSettings);
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          disabled={filteredPostTypes.length === 0}
        >
          {filteredPostTypes.length > 0 ? (
            filteredPostTypes.map(pt => (
              <option key={pt.name} value={pt.name}>{pt.label || pt.name}</option>
            ))
          ) : (
            <option value="">No options available</option>
          )}
        </select>
        {sourceType === 'cpts' && filteredPostTypes.length === 0 && (
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', fontStyle: 'italic' }}>
            No custom post types found. Create a custom post type first.
          </div>
        )}
      </label>
        
        {/* Pro-only source types removed from Free version */}
        {sourceType === 'user' && isPro && (
          <label style={{ display: 'block', marginBottom: '10px', marginTop: '10px' }}>
            User Role:
            <select
              value={settings.userRole || ''}
              onChange={(e) => {
                const updatedSettings = { ...settings, userRole: e.target.value };
                setSettings(updatedSettings);
                onUpdate(node.id, updatedSettings);
              }}
              style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
        >
              <option value="">All Roles</option>
              <option value="administrator">Administrator</option>
              <option value="editor">Editor</option>
              <option value="author">Author</option>
              <option value="contributor">Contributor</option>
              <option value="subscriber">Subscriber</option>
            </select>
            <div style={{ fontSize: '11px', color: '#718096', marginTop: '3px' }}>
              Select a role to filter users, or leave as "All Roles" to show all users
            </div>
          </label>
        )}
        
        {sourceType === 'comment' && isPro && (
          <>
            <label style={{ display: 'block', marginBottom: '10px', marginTop: '10px' }}>
              Post Type:
              <select
                value={settings.commentPostType || ''}
                onChange={(e) => {
                  const updatedSettings = { ...settings, commentPostType: e.target.value };
                  setSettings(updatedSettings);
                  onUpdate(node.id, updatedSettings);
                }}
                style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              >
                <option value="">All Post Types</option>
                {postTypes.length > 0 ? (
                  postTypes.map(pt => (
                    <option key={pt.name} value={pt.name}>{pt.label || pt.name}</option>
                  ))
                ) : (
                  <>
                    <option value="post">Posts</option>
                    <option value="page">Pages</option>
                  </>
                )}
              </select>
            </label>
            <label style={{ display: 'block', marginBottom: '10px', marginTop: '10px' }}>
              Comment Status:
              <select
                value={settings.commentStatus || 'approve'}
                onChange={(e) => {
                  const updatedSettings = { ...settings, commentStatus: e.target.value };
                  setSettings(updatedSettings);
                  onUpdate(node.id, updatedSettings);
                }}
                style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              >
                <option value="approve">Approved</option>
                <option value="hold">Pending</option>
                <option value="spam">Spam</option>
                <option value="trash">Trash</option>
                <option value="all">All Statuses</option>
              </select>
            </label>
          </>
        )}
        
        {sourceType === 'sql_table' && isPro && (
          <>
            <label style={{ display: 'block', marginBottom: '10px', marginTop: '10px' }}>
              Table Name:
              <input
                type="text"
                value={settings.tableName || ''}
                onChange={(e) => {
                  const updatedSettings = { ...settings, tableName: e.target.value };
                  setSettings(updatedSettings);
                  onUpdate(node.id, updatedSettings);
                }}
                placeholder="e.g., custom_table (without wp_ prefix)"
                style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              />
              <div style={{ fontSize: '11px', color: '#718096', marginTop: '3px' }}>
                Enter table name without wp_ prefix
              </div>
            </label>
          </>
        )}
        
        {sourceType === 'rest_api' && isPro && (
          <>
            <label style={{ display: 'block', marginBottom: '10px', marginTop: '10px' }}>
              API URL:
              <input
                type="text"
                value={settings.apiUrl || ''}
                onChange={(e) => {
                  const updatedSettings = { ...settings, apiUrl: e.target.value };
                  setSettings(updatedSettings);
                  onUpdate(node.id, updatedSettings);
                }}
                placeholder="https://api.example.com/endpoint"
                style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              />
            </label>
            <label style={{ display: 'block', marginBottom: '10px' }}>
              HTTP Method:
              <select
                value={settings.apiMethod || 'GET'}
                onChange={(e) => {
                  const updatedSettings = { ...settings, apiMethod: e.target.value };
                  setSettings(updatedSettings);
                  onUpdate(node.id, updatedSettings);
                }}
                style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              >
                <option value="GET">GET</option>
                <option value="POST">POST</option>
        </select>
      </label>
          </>
        )}
    </div>
  );
  };

  const renderFilterSettings = () => {
    // Separate standard and meta fields for grouping.
    const standardFields = fields.filter(f => f.type === 'standard');
    const metaFields = fields.filter(f => f.type === 'meta');
    
    return (
    <div>
      <label style={{ display: 'block', marginBottom: '10px' }}>
          Field:
          {!showCustomField ? (
            <select
              value={settings.field || ''}
              onChange={(e) => handleFieldChange(e.target.value)}
              style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
              disabled={loadingMetaKeys}
            >
              <option value="">-- Select Field --</option>
              {standardFields.length > 0 && (
                <optgroup label="Standard Fields">
                  {standardFields.map(field => (
                    <option key={field.key} value={field.key}>{field.label}</option>
                  ))}
                </optgroup>
              )}
              {metaFields.length > 0 && (
                <optgroup label="Custom Meta Fields">
                  {metaFields.map(field => (
                    <option key={field.key} value={field.key}>{field.label}</option>
                  ))}
                </optgroup>
              )}
              <option value="__custom__">-- Custom Field --</option>
            </select>
          ) : (
          <div>
        <input
          type="text"
          value={settings.field || ''}
              onChange={(e) => handleCustomFieldChange(e.target.value)}
              onBlur={() => {
                if (settings.field) {
                  onUpdate(node.id, settings);
                }
              }}
              placeholder="e.g. _price, stock_level, custom_field"
              style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
        />
            <button
              type="button"
              onClick={() => {
                setShowCustomField(false);
                setSettings({ ...settings, field: '', isCustomField: false });
              }}
              style={{
                marginTop: '5px',
                padding: '4px 8px',
                background: 'transparent',
                color: '#999',
                border: '1px solid #555',
                borderRadius: '4px',
                cursor: 'pointer',
                fontSize: '11px'
              }}
            >
              Use Dropdown Instead
            </button>
          </div>
        )}
        {loadingMetaKeys && (
          <div style={{ fontSize: '11px', color: '#999', marginTop: '5px' }}>Loading fields...</div>
        )}
      </label>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Operator:
        <select
          value={settings.operator || '='}
          onChange={(e) => {
            const newOperator = e.target.value;
            // Free version: only allow =, !=, LIKE
            const allowedOperators = ['=', '!=', 'LIKE'];
            if (!isPro && !allowedOperators.includes(newOperator)) {
              return; // Prevent change to Pro-only operators
            }
            setSettings({ ...settings, operator: newOperator });
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="=">=</option>
          <option value="!=">!=</option>
          <option value="LIKE">LIKE</option>
          {isPro && (
            <>
              <option value=">">&gt;</option>
              <option value=">=">&gt;=</option>
              <option value="<">&lt;</option>
              <option value="<=">&lt;=</option>
              <option value="EXISTS">EXISTS</option>
              <option value="NOT EXISTS">NOT EXISTS</option>
            </>
          )}
        </select>
        {!isPro && (
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', fontStyle: 'italic' }}>
            Advanced operators available in <a href="https://queryforgeplugin.com" target="_blank" rel="noopener noreferrer" style={{ color: '#5c4bde', textDecoration: 'none' }}>Pro</a>
          </div>
        )}
      </label>
      {!['EXISTS', 'NOT EXISTS'].includes(settings.operator) && (
        <label style={{ display: 'block', marginBottom: '10px', position: 'relative' }}>
          Value:
          <input
            type="text"
            value={settings.value || ''}
            onChange={(e) => setSettings({ ...settings, value: e.target.value })}
            placeholder={isPro ? "Enter value or use {{ tags }}" : "Enter static text... (Pro: use {{current_post_id}} for dynamic context)"}
            style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          />
          {!isPro && (
            <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', fontStyle: 'italic' }}>
              Dynamic tags available in <a href="https://queryforgeplugin.com" target="_blank" rel="noopener noreferrer" style={{ color: '#5c4bde', textDecoration: 'none' }}>Pro</a>
            </div>
          )}
          {isPro && (
            <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px' }}>
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setShowDynamicTags(!showDynamicTags);
                }}
                style={{
                  padding: '5px 8px',
                  background: showDynamicTags ? '#5c4bde' : '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  fontSize: '14px',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  minWidth: '32px',
                  height: '29px',
                }}
                title="Insert Dynamic Data"
              >
                ⚡
              </button>
            </div>
          )}
          {showDynamicTags && isPro && (
            <div
              ref={dynamicTagsRef}
              style={{
                position: 'absolute',
                top: '100%',
                left: 0,
                right: 0,
                marginTop: '5px',
                background: '#1e1e1e',
                border: '1px solid #444',
                borderRadius: '4px',
                padding: '10px',
                zIndex: 1000,
                boxShadow: '0 4px 6px rgba(0,0,0,0.3)',
                maxHeight: '300px',
                overflowY: 'auto',
              }}
              onClick={(e) => e.stopPropagation()}
            >
              <div style={{ fontSize: '12px', fontWeight: 'bold', marginBottom: '8px', color: '#999' }}>User</div>
              <button
                type="button"
                onClick={() => insertDynamicTag('{{ current_user_id }}')}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '6px 10px',
                  marginBottom: '4px',
                  background: '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  textAlign: 'left',
                  fontSize: '12px',
                }}
              >
                Current User ID
              </button>
              <button
                type="button"
                onClick={() => {
                  const key = prompt('Enter user meta key:');
                  if (key) {
                    insertDynamicTag(`{{ user_meta:${key} }}`);
                  }
                }}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '6px 10px',
                  marginBottom: '8px',
                  background: '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  textAlign: 'left',
                  fontSize: '12px',
                }}
              >
                User Meta...
              </button>

              <div style={{ fontSize: '12px', fontWeight: 'bold', marginBottom: '8px', marginTop: '8px', color: '#999' }}>Post</div>
              <button
                type="button"
                onClick={() => insertDynamicTag('{{ current_post_id }}')}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '6px 10px',
                  marginBottom: '4px',
                  background: '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  textAlign: 'left',
                  fontSize: '12px',
                }}
              >
                Current Post ID
              </button>
              <button
                type="button"
                onClick={() => insertDynamicTag('{{ current_author_id }}')}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '6px 10px',
                  marginBottom: '8px',
                  background: '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  textAlign: 'left',
                  fontSize: '12px',
                }}
              >
                Current Post Author
              </button>

              <div style={{ fontSize: '12px', fontWeight: 'bold', marginBottom: '8px', marginTop: '8px', color: '#999' }}>System</div>
              <button
                type="button"
                onClick={() => {
                  const key = prompt('Enter URL parameter key:');
                  if (key) {
                    insertDynamicTag(`{{ url_param:${key} }}`);
                  }
                }}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '6px 10px',
                  marginBottom: '4px',
                  background: '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  textAlign: 'left',
                  fontSize: '12px',
                }}
              >
                URL Parameter...
              </button>
              <button
                type="button"
                onClick={() => insertDynamicTag('{{ current_date }}')}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '6px 10px',
                  marginBottom: '4px',
                  background: '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  textAlign: 'left',
                  fontSize: '12px',
                }}
              >
                Today's Date
              </button>
            </div>
          )}
        </label>
      )}
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Value Type:
        <select
          value={settings.valueType || 'CHAR'}
          onChange={(e) => setSettings({ ...settings, valueType: e.target.value })}
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="CHAR">Text</option>
          <option value="NUMERIC">Number</option>
          <option value="DATE">Date</option>
        </select>
      </label>
    </div>
  );
  };

  const renderSortSettings = () => (
    <div>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Sort By:
        <select
          value={settings.field || 'date'}
          onChange={(e) => {
            const newField = e.target.value;
            
            // Pro-only sort options are not available in Free version
            const proOnlySorts = ['rand', 'menu_order', 'meta_value', 'meta_value_num'];
            if (!isPro && proOnlySorts.includes(newField)) {
              return; // Prevent change - Pro sorts not available
            }
            
            const updatedSettings = { ...settings, field: newField };
            // Clear meta_key if not using meta_value
            if (newField !== 'meta_value' && newField !== 'meta_value_num') {
              delete updatedSettings.meta_key;
            }
            setSettings(updatedSettings);
            onUpdate(node.id, updatedSettings);
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
        >
          <option value="ID">ID</option>
          <option value="title">Title</option>
          <option value="date">Date</option>
          {isPro && (
            <>
              <option value="menu_order">Menu Order</option>
              <option value="rand">Random</option>
              <option value="meta_value">Meta Value (Text)</option>
              <option value="meta_value_num">Meta Value (Number)</option>
            </>
          )}
        </select>
        {!isPro && (
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', fontStyle: 'italic' }}>
            Advanced sorting available in <a href="https://queryforgeplugin.com" target="_blank" rel="noopener noreferrer" style={{ color: '#5c4bde', textDecoration: 'none' }}>Pro</a>
          </div>
        )}
      </label>
      {(settings.field === 'meta_value' || settings.field === 'meta_value_num') && (
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Meta Key:
          <input
            type="text"
            value={settings.meta_key || ''}
            onChange={(e) => {
              const updatedSettings = { ...settings, meta_key: e.target.value };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            placeholder="Enter meta key name"
            style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          />
        </label>
      )}
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Direction:
        <div style={{ display: 'flex', gap: '10px', marginTop: '5px' }}>
          <button
            type="button"
            onClick={() => {
              const updatedSettings = { ...settings, direction: 'ASC' };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            style={{
              flex: 1,
              padding: '8px',
              background: settings.direction === 'ASC' ? '#5c4bde' : '#2d3748',
              color: '#fff',
              border: '1px solid #555',
              borderRadius: '4px',
              cursor: 'pointer',
            }}
          >
            ASC ↑
          </button>
          <button
            type="button"
            onClick={() => {
              const updatedSettings = { ...settings, direction: 'DESC' };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            style={{
              flex: 1,
              padding: '8px',
              background: settings.direction === 'DESC' ? '#5c4bde' : '#2d3748',
              color: '#fff',
              border: '1px solid #555',
              borderRadius: '4px',
              cursor: 'pointer',
            }}
          >
            DESC ↓
          </button>
        </div>
      </label>
    </div>
  );

  const renderInclusionExclusionSettings = () => {
    const insertDynamicTag = (tag) => {
      // This will be handled by the input fields with their own dynamic tag buttons
      return tag;
    };

    return (
      <div style={{ position: 'relative' }}>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Include Post IDs (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.postIn || ''}
              onChange={(e) => {
                // Accept any text input including commas - no restrictions
                const newSettings = { ...settings, postIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder={isPro ? "e.g., 1,2,3 or {{ current_post_id }}" : "e.g., 1,2,3 (Pro: use {{current_post_id}} for dynamic context)"}
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
            {isPro && (
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setActiveField('postIn');
                  setShowDynamicTags(!showDynamicTags || activeField !== 'postIn');
                }}
                style={{
                  padding: '5px 8px',
                  background: (showDynamicTags && activeField === 'postIn') ? '#5c4bde' : '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  fontSize: '14px',
                  minWidth: '32px',
                  height: '29px',
                }}
                title="Insert Dynamic Data"
              >
                ⚡
              </button>
            )}
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Exclude Post IDs (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.postNotIn || ''}
              onChange={(e) => {
                // Accept any text input including commas - no restrictions
                const newSettings = { ...settings, postNotIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder={isPro ? "e.g., 1,2,3 or {{ current_post_id }}" : "e.g., 1,2,3 (Pro: use {{current_post_id}} for dynamic context)"}
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
            {isPro && (
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setActiveField('postNotIn');
                  setShowDynamicTags(!showDynamicTags || activeField !== 'postNotIn');
                }}
                style={{
                  padding: '5px 8px',
                  background: (showDynamicTags && activeField === 'postNotIn') ? '#5c4bde' : '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  fontSize: '14px',
                  minWidth: '32px',
                  height: '29px',
                }}
                title="Insert Dynamic Data"
              >
                ⚡
              </button>
            )}
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Author IDs Include (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.authorIn || ''}
              onChange={(e) => {
                // Accept any text input including commas - no restrictions
                const newSettings = { ...settings, authorIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder={isPro ? "e.g., 1,2,3 or {{ current_author_id }}" : "e.g., 1,2,3 (Pro: use {{current_author_id}} for dynamic context)"}
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
            {isPro && (
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setActiveField('authorIn');
                  setShowDynamicTags(!showDynamicTags || activeField !== 'authorIn');
                }}
                style={{
                  padding: '5px 8px',
                  background: (showDynamicTags && activeField === 'authorIn') ? '#5c4bde' : '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  fontSize: '14px',
                  minWidth: '32px',
                  height: '29px',
                }}
                title="Insert Dynamic Data"
              >
                ⚡
              </button>
            )}
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Author IDs Exclude (comma-separated):
          <div style={{ display: 'flex', alignItems: 'center', gap: '5px', marginTop: '5px', position: 'relative' }}>
            <input
              type="text"
              value={settings.authorNotIn || ''}
              onChange={(e) => {
                // Accept any text input including commas - no restrictions
                const newSettings = { ...settings, authorNotIn: e.target.value };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
              }}
              placeholder={isPro ? "e.g., 1,2,3 or {{ current_author_id }}" : "e.g., 1,2,3 (Pro: use {{current_author_id}} for dynamic context)"}
              style={{ flex: 1, padding: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
            />
            {isPro && (
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setActiveField('authorNotIn');
                  setShowDynamicTags(!showDynamicTags || activeField !== 'authorNotIn');
                }}
                style={{
                  padding: '5px 8px',
                  background: (showDynamicTags && activeField === 'authorNotIn') ? '#5c4bde' : '#2d3748',
                  color: '#fff',
                  border: '1px solid #555',
                  borderRadius: '4px',
                  cursor: 'pointer',
                  fontSize: '14px',
                  minWidth: '32px',
                  height: '29px',
                }}
                title="Insert Dynamic Data"
              >
                ⚡
              </button>
            )}
          </div>
        </label>
        <label style={{ display: 'flex', alignItems: 'center', marginBottom: '10px', gap: '10px' }}>
          <input
            type="checkbox"
            checked={settings.ignoreStickyPosts !== false}
            onChange={(e) => {
              const newSettings = { ...settings, ignoreStickyPosts: e.target.checked };
              setSettings(newSettings);
              onUpdate(node.id, newSettings);
            }}
            style={{ width: 'auto' }}
          />
          <span>Ignore Sticky Posts</span>
        </label>
        {showDynamicTags && activeField && isPro && (
          <div
            ref={dynamicTagsRef}
            style={{
              position: 'absolute',
              top: '100%',
              left: 0,
              right: 0,
              marginTop: '5px',
              background: '#1e1e1e',
              border: '1px solid #444',
              borderRadius: '4px',
              padding: '10px',
              zIndex: 10001,
              boxShadow: '0 4px 6px rgba(0,0,0,0.3)',
              maxHeight: '300px',
              overflowY: 'auto',
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <div style={{ fontSize: '12px', fontWeight: 'bold', marginBottom: '8px', color: '#999' }}>Post</div>
            <button
              type="button"
              onClick={() => {
                if (!activeField) return;
                const currentValue = settings[activeField] || '';
                const newValue = currentValue + (currentValue ? ', ' : '') + '{{ current_post_id }}';
                const newSettings = { ...settings, [activeField]: newValue };
                setSettings(newSettings);
                onUpdate(node.id, newSettings);
                setShowDynamicTags(false);
                setActiveField(null);
              }}
              style={{
                display: 'block',
                width: '100%',
                padding: '6px 10px',
                marginBottom: '4px',
                background: '#2d3748',
                color: '#fff',
                border: '1px solid #555',
                borderRadius: '4px',
                cursor: 'pointer',
                textAlign: 'left',
                fontSize: '12px',
              }}
            >
              Current Post ID
            </button>
            <button
              type="button"
              onClick={() => {
                if (!activeField) return;
                const taxonomy = prompt('Enter taxonomy name (e.g., category, post_tag):');
                if (taxonomy && (activeField === 'postIn' || activeField === 'postNotIn')) {
                  const currentValue = settings[activeField] || '';
                  const newValue = currentValue + (currentValue ? ', ' : '') + `{{ current_post_terms:${taxonomy} }}`;
                  const newSettings = { ...settings, [activeField]: newValue };
                  setSettings(newSettings);
                  onUpdate(node.id, newSettings);
                  setShowDynamicTags(false);
                  setActiveField(null);
                }
              }}
              style={{
                display: 'block',
                width: '100%',
                padding: '6px 10px',
                marginBottom: '8px',
                background: '#2d3748',
                color: '#fff',
                border: '1px solid #555',
                borderRadius: '4px',
                cursor: 'pointer',
                textAlign: 'left',
                fontSize: '12px',
              }}
            >
              Current Post Terms...
            </button>
            <div style={{ fontSize: '12px', fontWeight: 'bold', marginBottom: '8px', marginTop: '8px', color: '#999' }}>User</div>
            <button
              type="button"
              onClick={() => {
                if (!activeField) return;
                if (activeField === 'authorIn' || activeField === 'authorNotIn') {
                  const currentValue = settings[activeField] || '';
                  const newValue = currentValue + (currentValue ? ', ' : '') + '{{ current_author_id }}';
                  const newSettings = { ...settings, [activeField]: newValue };
                  setSettings(newSettings);
                  onUpdate(node.id, newSettings);
                  setShowDynamicTags(false);
                  setActiveField(null);
                }
              }}
              style={{
                display: 'block',
                width: '100%',
                padding: '6px 10px',
                marginBottom: '4px',
                background: '#2d3748',
                color: '#fff',
                border: '1px solid #555',
                borderRadius: '4px',
                cursor: 'pointer',
                textAlign: 'left',
                fontSize: '12px',
              }}
            >
              Current Post Author
            </button>
            <button
              type="button"
              onClick={() => {
                if (!activeField) return;
                if (activeField === 'authorIn' || activeField === 'authorNotIn') {
                  const currentValue = settings[activeField] || '';
                  const newValue = currentValue + (currentValue ? ', ' : '') + '{{ current_user_id }}';
                  const newSettings = { ...settings, [activeField]: newValue };
                  setSettings(newSettings);
                  onUpdate(node.id, newSettings);
                  setShowDynamicTags(false);
                  setActiveField(null);
                }
              }}
              style={{
                display: 'block',
                width: '100%',
                padding: '6px 10px',
                marginBottom: '4px',
                background: '#2d3748',
                color: '#fff',
                border: '1px solid #555',
                borderRadius: '4px',
                cursor: 'pointer',
                textAlign: 'left',
                fontSize: '12px',
              }}
            >
              Current User ID
            </button>
          </div>
        )}
      </div>
    );
  };

  const renderLogicSettings = () => (
    <div>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Relation:
        <select
          value={settings.relation || 'AND'}
          onChange={(e) => {
            const newRelation = e.target.value;
            // Free version: only allow AND
            if (!isPro && newRelation === 'OR') {
              return; // Prevent change to OR
            }
            setSettings({ ...settings, relation: newRelation });
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="AND">AND</option>
          {isPro && <option value="OR">OR</option>}
        </select>
      </label>
      {!isPro && (
        <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', fontStyle: 'italic' }}>
          OR logic available in <a href="https://queryforgeplugin.com" target="_blank" rel="noopener noreferrer" style={{ color: '#5c4bde', textDecoration: 'none' }}>Pro</a>
        </div>
      )}
    </div>
  );

  const renderJoinSettings = () => {
    // Join nodes are PRO-only - not available in Free version
    if (!isPro) {
      return (
        <div>
          <div style={{ fontSize: '12px', color: '#999', marginTop: '10px' }}>
            SQL Joins are a Pro feature. <a href="https://queryforgeplugin.com" target="_blank" rel="noopener noreferrer" style={{ color: '#5c4bde', textDecoration: 'none' }}>Upgrade to Pro</a> to join custom database tables.
          </div>
        </div>
      );
    }
    
    return (
      <div>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Table Name:
          <input
            type="text"
            value={settings.table || ''}
            onChange={(e) => {
              const updatedSettings = { 
                ...settings, 
                table: e.target.value,
                // Auto-set alias to table name if alias is empty
                alias: settings.alias || e.target.value
              };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            placeholder="e.g., postmeta (without wp_ prefix)"
            style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          />
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '3px' }}>
            Enter table name without wp_ prefix (e.g., "postmeta" not "wp_postmeta")
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Alias (optional):
          <input
            type="text"
            value={settings.alias || settings.table || ''}
            onChange={(e) => {
              const updatedSettings = { ...settings, alias: e.target.value };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            placeholder="Leave empty to use table name"
            style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          />
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '3px' }}>
            Optional alias for the table in the query
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Left Column (from wp_posts):
          <input
            type="text"
            value={settings.on?.left || 'ID'}
            onChange={(e) => {
              const updatedSettings = { 
                ...settings, 
                on: { ...settings.on, left: e.target.value }
              };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            placeholder="ID"
            style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          />
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '3px' }}>
            Column from wp_posts table (default: ID)
          </div>
        </label>
        <label style={{ display: 'block', marginBottom: '10px' }}>
          Right Column (from joined table):
          <input
            type="text"
            value={settings.on?.right || 'post_id'}
            onChange={(e) => {
              const updatedSettings = { 
                ...settings, 
                on: { ...settings.on, right: e.target.value }
              };
              setSettings(updatedSettings);
              onUpdate(node.id, updatedSettings);
            }}
            placeholder="post_id"
            style={{ width: '100%', padding: '5px', marginTop: '5px', background: '#2d3748', color: '#fff', border: '1px solid #555' }}
          />
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '3px' }}>
            Column from joined table (default: post_id)
          </div>
        </label>
      </div>
    );
  };

  const renderTargetSettings = () => (
    <div>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Posts Per Page:
        <input
          type="number"
          value={settings.postsPerPage || 10}
          onChange={(e) => setSettings({ ...settings, postsPerPage: parseInt(e.target.value) || 10 })}
          min="1"
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        />
      </label>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Order By:
        <select
          value={settings.orderBy || 'date'}
          onChange={(e) => {
            const newOrderBy = e.target.value;
            // Free version: only allow date, title
            const allowedOrderBy = ['date', 'title'];
            if (!isPro && !allowedOrderBy.includes(newOrderBy)) {
              return; // Prevent change to Pro-only options
            }
            setSettings({ ...settings, orderBy: newOrderBy });
          }}
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="date">Date</option>
          <option value="title">Title</option>
          {isPro && (
            <>
              <option value="menu_order">Menu Order</option>
              <option value="meta_value">Meta Value</option>
            </>
          )}
        </select>
        {!isPro && (
          <div style={{ fontSize: '11px', color: '#718096', marginTop: '5px', fontStyle: 'italic' }}>
            Advanced sorting available in <a href="https://queryforgeplugin.com" target="_blank" rel="noopener noreferrer" style={{ color: '#5c4bde', textDecoration: 'none' }}>Pro</a>
          </div>
        )}
      </label>
      <label style={{ display: 'block', marginBottom: '10px' }}>
        Order:
        <select
          value={settings.order || 'DESC'}
          onChange={(e) => setSettings({ ...settings, order: e.target.value })}
          style={{ width: '100%', padding: '5px', marginTop: '5px' }}
        >
          <option value="ASC">ASC</option>
          <option value="DESC">DESC</option>
        </select>
      </label>
    </div>
  );

  return (
      <div 
      data-qf-settings-panel
      style={{
      position: 'absolute',
      right: '20px',
      top: '20px',
      width: '300px',
      background: '#1e1e1e',
      border: '1px solid #333',
      borderRadius: '8px',
      padding: '20px',
      color: '#fff',
      zIndex: 1000,
      maxHeight: '80vh',
      overflowY: 'auto'
      }}
      onClick={(e) => e.stopPropagation()}
      onMouseDown={(e) => e.stopPropagation()}
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '15px' }}>
        <h3 style={{ margin: 0 }}>Node Settings</h3>
        <button
          onClick={onClose}
          style={{ background: 'transparent', border: 'none', color: '#fff', cursor: 'pointer' }}
        >
          ×
        </button>
      </div>
      
      {node.type === 'source' && renderSourceSettings()}
      {node.type === 'filter' && renderFilterSettings()}
      {node.type === 'sort' && renderSortSettings()}
      {node.type === 'inclusionExclusion' && renderInclusionExclusionSettings()}
      {node.type === 'join' && renderJoinSettings()}
      {node.type === 'logic' && renderLogicSettings()}
      {node.type === 'target' && renderTargetSettings()}

      <button
        onClick={(e) => {
          e.stopPropagation();
          e.preventDefault();
          handleSave(e);
          onClose(); // Close panel after applying
        }}
        type="button"
        style={{
          width: '100%',
          padding: '10px',
          background: '#5c4bde',
          color: '#fff',
          border: 'none',
          borderRadius: '4px',
          cursor: 'pointer',
          marginTop: '15px'
        }}
      >
        Apply
      </button>
      
      {/* Upsell Modal */}
      <UpsellModal
        isOpen={upsellModal.isOpen}
        onClose={() => setUpsellModal({ isOpen: false, featureName: '', description: '' })}
        featureName={upsellModal.featureName}
        description={upsellModal.description}
      />
    </div>
  );
};

export default NodeSettingsPanel;

