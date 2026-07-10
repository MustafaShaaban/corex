import './style.scss';
import metadata from './block.json';
import { registerFlowBlock } from '../flowBlockEditor.js';

registerFlowBlock( metadata, 'subscribe' );
