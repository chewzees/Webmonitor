import { EventEmitter } from 'events';

export type AppEventType =
  | 'check.completed'
  | 'status.changed'
  | 'website.updated';

export interface AppEvent {
  type: AppEventType;
  payload: Record<string, unknown>;
  at: string;
}

class EventBus extends EventEmitter {
  publish(type: AppEventType, payload: Record<string, unknown>): void {
    const event: AppEvent = {
      type,
      payload,
      at: new Date().toISOString(),
    };
    this.emit('event', event);
    this.emit(type, event);
  }
}

export const eventBus = new EventBus();
